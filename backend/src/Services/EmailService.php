<?php

namespace App\Services;

use App\Models\Appointment;
use Resend\Client;

/**
 * EmailService - Envia notificações por e-mail ao paciente via Resend
 *
 * Falhas de envio nunca devem quebrar o fluxo principal (criar/confirmar
 * agendamento) — por isso todo método aqui captura suas próprias exceções
 * e apenas loga o erro.
 *
 * IMPORTANTE (modo sandbox do Resend): sem domínio verificado, o Resend só
 * entrega e-mails para o endereço cadastrado na conta. Depois de verificar
 * um domínio próprio, troque RESEND_FROM_EMAIL no .env e a restrição some.
 */
class EmailService
{
    private ?Client $client;
    private string $fromEmail;
    private string $fromName;
    private bool $enabled;

    public function __construct()
    {
        $apiKey = $_ENV['RESEND_API_KEY'] ?? '';
        $this->enabled = !empty($apiKey);
        $this->client = $this->enabled ? \Resend::client($apiKey) : null;

        $this->fromEmail = $_ENV['RESEND_FROM_EMAIL'] ?? 'onboarding@resend.dev';
        $this->fromName  = $_ENV['RESEND_FROM_NAME'] ?? 'ClinicaAme';
    }

    /**
     * Notifica o paciente que um agendamento foi criado (status 'scheduled')
     */
    public function sendAppointmentCreated(Appointment $appointment): void
    {
        $this->send(
            $appointment,
            'Agendamento realizado — ClinicaAme',
            'foi agendada',
            'Assim que possível, um profissional da clínica irá confirmar sua sessão.'
        );
    }

    /**
     * Notifica o paciente que o agendamento foi confirmado
     */
    public function sendAppointmentConfirmed(Appointment $appointment): void
    {
        $this->send(
            $appointment,
            'Agendamento confirmado — ClinicaAme',
            'foi confirmada',
            'Te esperamos no horário combinado.'
        );
    }

    private function send(Appointment $appointment, string $subject, string $verb, string $footer): void
    {
        if (!$this->enabled) {
            return;
        }

        $patient = $appointment->getPatient();
        if (!$patient || empty($patient->getEmail())) {
            return;
        }

        try {
            $this->client->emails->send([
                'from'    => "{$this->fromName} <{$this->fromEmail}>",
                'to'      => [$patient->getEmail()],
                'subject' => $subject,
                'html'    => $this->buildHtml($appointment, $patient->getName(), $verb, $footer),
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao enviar e-mail de agendamento: " . $e->getMessage());
        }
    }

    private function buildHtml(Appointment $appointment, string $patientName, string $verb, string $footer): string
    {
        $serviceName = $appointment->getService()?->getName() ?? 'Sessão';
        $professionalName = $appointment->getProfessional()?->getName() ?? '';
        $when = $appointment->getFormattedStartTime();

        return <<<HTML
            <div style="font-family: Arial, sans-serif; color: #2d1a23; max-width: 480px;">
                <h2 style="color: #b87494;">ClinicaAme</h2>
                <p>Olá, {$patientName}!</p>
                <p>Sua sessão de <strong>{$serviceName}</strong> com <strong>{$professionalName}</strong> {$verb}:</p>
                <p style="font-size: 18px; font-weight: bold;">{$when}</p>
                <p>{$footer}</p>
            </div>
        HTML;
    }
}
