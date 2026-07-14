<?php
namespace App\Support;

use DateTime;
use InvalidArgumentException;

/**
 * RentalPeriod - fonte única de verdade dos blocos de horário da sublocação
 *
 * Usado tanto pra calcular start_time/end_time de uma reserva quanto
 * pra validar a trava de recorrência (só bloco fechado pode ser fixo)
 */
class RentalPeriod {
    public const VALID_PERIODS = ['manha', 'tarde', 'noite', 'avulso'];

    // Períodos que fecham um bloco de 4h — só esses podem ser recorrentes
    public const RECURRING_ELIGIBLE_PERIODS = ['manha', 'tarde', 'noite'];

    // Janela de funcionamento — o avulso pode ser marcado em qualquer hora
    // cheia dentro desse intervalo (última reserva possível: 20h-21h)
    public const AVULSO_MIN_HOUR = 8;
    public const AVULSO_MAX_HOUR = 20;

    private const HOURS = [
        'manha' => ['08:00:00', '12:00:00'],
        'tarde' => ['12:00:00', '16:00:00'],
        'noite' => ['16:00:00', '20:00:00'],
    ];

    /**
     * @throws InvalidArgumentException Se o período não existir
     */
    public static function assertValid(string $period): void {
        if (!in_array($period, self::VALID_PERIODS, true)) {
            throw new InvalidArgumentException(
                "Período inválido: '{$period}'. Use manha, tarde, noite ou avulso"
            );
        }
    }

    /**
     * @throws InvalidArgumentException Se a hora estiver fora do horário de funcionamento
     */
    public static function assertValidAvulsoHour(int $hour): void {
        if ($hour < self::AVULSO_MIN_HOUR || $hour > self::AVULSO_MAX_HOUR) {
            throw new InvalidArgumentException(
                "Horário inválido: o avulso só pode ser marcado entre " .
                self::AVULSO_MIN_HOUR . "h e " . (self::AVULSO_MAX_HOUR + 1) . "h"
            );
        }
    }

    public static function isEligibleForRecurrence(string $period): bool {
        return in_array($period, self::RECURRING_ELIGIBLE_PERIODS, true);
    }

    /**
     * Calcula o start_time/end_time (DateTime) de uma reserva a partir da data e do período
     *
     * Para 'avulso', $avulsoHour é obrigatório — é a hora cheia específica marcada
     * (ex: 18 → reserva das 18h às 19h), já que o avulso não tem mais um horário fixo
     *
     * @return array{0: DateTime, 1: DateTime} [$startTime, $endTime]
     */
    public static function toDateTimeRange(DateTime $date, string $period, ?int $avulsoHour = null): array {
        self::assertValid($period);
        $dateStr = $date->format('Y-m-d');

        if ($period === 'avulso') {
            if ($avulsoHour === null) {
                throw new InvalidArgumentException('O horário (hour) é obrigatório para reservas avulsas');
            }
            self::assertValidAvulsoHour($avulsoHour);

            $startHour = str_pad((string) $avulsoHour, 2, '0', STR_PAD_LEFT) . ':00:00';
            $endHour   = str_pad((string) ($avulsoHour + 1), 2, '0', STR_PAD_LEFT) . ':00:00';

            return [
                new DateTime("{$dateStr} {$startHour}"),
                new DateTime("{$dateStr} {$endHour}"),
            ];
        }

        [$startHour, $endHour] = self::HOURS[$period];

        return [
            new DateTime("{$dateStr} {$startHour}"),
            new DateTime("{$dateStr} {$endHour}"),
        ];
    }

    public static function label(string $period): string {
        return [
            'manha'  => 'Manhã (08h-12h)',
            'tarde'  => 'Tarde (12h-16h)',
            'noite'  => 'Noite (16h-20h)',
            'avulso' => 'Avulso (horário específico)',
        ][$period] ?? $period;
    }
}
