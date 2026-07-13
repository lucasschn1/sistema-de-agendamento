-- =============================================
-- SISTEMA DE AGENDAMENTO - CLÍNICA DE PSICOLOGIA
-- Alterações:
--   - Soft delete em todas as tabelas
--   - Recorrência com tabela dedicada (recurrence_groups)
--   - CRP opcional (suporte a psicopedagogos e outros profissionais)
--   - ON DELETE RESTRICT (proteção contra deleção acidental)
--   - Trigger de conflito de horário
-- =============================================

CREATE DATABASE IF NOT EXISTS agenda_clinica;
USE agenda_clinica;

-- =============================================
-- TABELA: users
-- =============================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Dados Básicos
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    phone VARCHAR(20),
    birthdate DATE,

    -- Tipo de Usuário
    role ENUM('admin', 'patient', 'professional') NOT NULL DEFAULT 'patient',
    -- 'professional' substitui 'psychologist' para abranger
    -- psicólogos, psicopedagogos e outros profissionais

    -- Campos específicos de Profissional (NULL para pacientes)
    -- 'council_id' substitui 'crp' — pode ser CRP, CRP-SP, etc.
    -- NULL para profissionais sem registro em conselho (ex: psicopedagogos)
    professional_type VARCHAR(50) COMMENT 'Ex: Psicólogo, Psicopedagogo, Neuropsicólogo',
    council_id VARCHAR(20) COMMENT 'CRP, CRM, etc. NULL se não aplicável',
    specialty VARCHAR(100),
    bio TEXT,

    -- Controle
    active BOOLEAN DEFAULT TRUE,

    -- Soft Delete
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_email (email),
    INDEX idx_cpf (cpf),
    INDEX idx_role (role),
    INDEX idx_active (active),
    INDEX idx_deleted_at (deleted_at)  -- acelera o filtro de soft delete
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Usuários: pacientes, profissionais (psicólogos, psicopedagogos, etc.) e admins';


-- =============================================
-- TABELA: services (Catálogo de Serviços)
-- =============================================
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,

    name VARCHAR(100) NOT NULL,
    description TEXT,

    price DECIMAL(10, 2) NOT NULL,
    duration_minutes INT DEFAULT 50,

    category VARCHAR(50) COMMENT 'Individual, Casal, Familiar, Grupo, Avaliação, Orientação',

    -- Controle
    active BOOLEAN DEFAULT TRUE,

    -- Soft Delete
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_active (active),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Catálogo de serviços oferecidos pela clínica';


-- =============================================
-- TABELA: recurrence_groups
-- Define a regra de uma recorrência.
-- Cada appointment recorrente aponta para um grupo.
-- =============================================
CREATE TABLE recurrence_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Quem participa
    patient_id INT NOT NULL,
    professional_id INT NOT NULL,
    service_id INT NOT NULL,

    -- Regra de recorrência
    type ENUM('semanal', 'quinzenal') NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Domingo, 1=Segunda ... 6=Sábado',
    start_hour TIME NOT NULL COMMENT 'Horário de início de cada sessão',

    -- Período da recorrência
    start_date DATE NOT NULL,
    end_date DATE NULL COMMENT 'NULL = sem data de fim definida',

    -- Controle
    active BOOLEAN DEFAULT TRUE COMMENT 'FALSE = recorrência pausada/encerrada',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Chaves Estrangeiras
    -- RESTRICT: impede deletar usuário/serviço com recorrências ativas
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,

    -- Índices
    INDEX idx_patient_id (patient_id),
    INDEX idx_professional_id (professional_id),
    INDEX idx_service_id (service_id),
    INDEX idx_active (active),
    INDEX idx_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Define as regras de recorrência (semanal/quinzenal) de um conjunto de agendamentos';


-- =============================================
-- TABELA: appointments (Agendamentos)
-- =============================================
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,

    -- Relacionamentos
    patient_id INT NOT NULL,
    professional_id INT NOT NULL,
    service_id INT NOT NULL,

    -- Recorrência (NULL = sessão única)
    recurrence_group_id INT NULL,
    recurrence_type ENUM('unico', 'semanal', 'quinzenal') DEFAULT 'unico',

    -- Data e Hora
    -- end_time é calculado automaticamente pelo trigger
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 50,

    -- Financeiro
    price DECIMAL(10, 2) NOT NULL,
    paid BOOLEAN DEFAULT FALSE,
    payment_method VARCHAR(50) COMMENT 'PIX, Dinheiro, Cartão, etc.',
    payment_date DATE,

    -- Status
    -- 'no_show' adicionado: paciente não compareceu
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    cancellation_reason TEXT NULL COMMENT 'Motivo do cancelamento, se aplicável',

    -- Observações
    notes TEXT,

    -- Soft Delete
    -- Cancelamentos ficam registrados; só some com delete explícito do admin
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Chaves Estrangeiras
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
    FOREIGN KEY (recurrence_group_id) REFERENCES recurrence_groups(id) ON DELETE SET NULL,
    -- SET NULL: se o grupo for deletado, os appointments ficam como sessões avulsas

    -- Índices
    INDEX idx_patient_id (patient_id),
    INDEX idx_professional_id (professional_id),
    INDEX idx_service_id (service_id),
    INDEX idx_recurrence_group_id (recurrence_group_id),
    INDEX idx_pro_time (professional_id, start_time),   -- conflito de horário
    INDEX idx_patient_time (patient_id, start_time),
    INDEX idx_status (status),
    INDEX idx_start_time (start_time),
    INDEX idx_payment (paid, payment_date),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Agendamentos individuais — únicos ou parte de uma recorrência';


-- =============================================
-- TABELA: appointment_history (Auditoria de status)
-- Registra quem mudou o status de um agendamento e quando
-- =============================================
CREATE TABLE appointment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,

    appointment_id INT NOT NULL,
    action VARCHAR(30) NOT NULL COMMENT 'created, confirmed, completed, cancelled, no_show, rescheduled',
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NULL,
    changed_by_user_id INT NULL COMMENT 'NULL se o usuário foi removido depois',
    reason TEXT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_appointment_id (appointment_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Histórico de mudanças de status de um agendamento (auditoria)';


-- =============================================
-- TRIGGERS
-- =============================================

DELIMITER //

-- 1. Calcula end_time no INSERT
CREATE TRIGGER trg_appointments_end_time_insert
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    SET NEW.end_time = DATE_ADD(NEW.start_time, INTERVAL NEW.duration_minutes MINUTE);
END //

-- 2. Recalcula end_time no UPDATE se horário ou duração mudarem
CREATE TRIGGER trg_appointments_end_time_update
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
    IF NEW.start_time != OLD.start_time OR NEW.duration_minutes != OLD.duration_minutes THEN
        SET NEW.end_time = DATE_ADD(NEW.start_time, INTERVAL NEW.duration_minutes MINUTE);
    END IF;
END //

-- 3. Valida conflito de horário para o profissional no INSERT
CREATE TRIGGER trg_check_conflict_insert
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE v_conflict INT DEFAULT 0;

    SELECT COUNT(*) INTO v_conflict
    FROM appointments
    WHERE professional_id = NEW.professional_id
      AND status NOT IN ('cancelled', 'no_show')
      AND deleted_at IS NULL
      AND start_time < DATE_ADD(NEW.start_time, INTERVAL NEW.duration_minutes MINUTE)
      AND end_time > NEW.start_time;

    IF v_conflict > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Conflito de horário: profissional já possui agendamento neste período';
    END IF;
END //

-- 4. Valida conflito de horário para o profissional no UPDATE
CREATE TRIGGER trg_check_conflict_update
BEFORE UPDATE ON appointments
FOR EACH ROW
BEGIN
    DECLARE v_conflict INT DEFAULT 0;

    -- Só valida se horário ou profissional mudaram
    IF NEW.start_time != OLD.start_time
    OR NEW.duration_minutes != OLD.duration_minutes
    OR NEW.professional_id != OLD.professional_id THEN

        SELECT COUNT(*) INTO v_conflict
        FROM appointments
        WHERE professional_id = NEW.professional_id
          AND id != NEW.id                              -- ignora o próprio registro
          AND status NOT IN ('cancelled', 'no_show')
          AND deleted_at IS NULL
          AND start_time < DATE_ADD(NEW.start_time, INTERVAL NEW.duration_minutes MINUTE)
          AND end_time > NEW.start_time;

        IF v_conflict > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Conflito de horário: profissional já possui agendamento neste período';
        END IF;
    END IF;
END //

DELIMITER ;


-- =============================================
-- VIEWS
-- =============================================

-- View: Agenda Completa (apenas registros não deletados)
CREATE VIEW vw_agenda AS
SELECT
    a.id,
    a.start_time,
    a.end_time,
    a.duration_minutes,
    a.status,
    a.recurrence_type,
    a.recurrence_group_id,
    a.notes,
    a.created_at,

    -- Paciente
    a.patient_id,
    p.name AS patient_name,
    p.email AS patient_email,
    p.phone AS patient_phone,

    -- Profissional
    a.professional_id,
    pr.name AS professional_name,
    pr.professional_type,
    pr.council_id,
    pr.specialty,

    -- Serviço
    a.service_id,
    s.name AS service_name,
    s.category AS service_category,
    s.price AS service_base_price,

    -- Financeiro
    a.price,
    a.paid,
    a.payment_method,
    a.payment_date,
    a.cancellation_reason

FROM appointments a
INNER JOIN users p  ON a.patient_id = p.id
INNER JOIN users pr ON a.professional_id = pr.id
INNER JOIN services s ON a.service_id = s.id
WHERE a.deleted_at IS NULL;   -- soft delete


-- View: Agenda para Calendário (frontend)
-- Retorna apenas os campos necessários para renderizar o calendário
CREATE VIEW vw_calendar AS
SELECT
    a.id,
    a.start_time,
    a.end_time,
    a.status,
    a.recurrence_type,
    a.recurrence_group_id,
    p.name AS patient_name,
    pr.name AS professional_name,
    s.name AS service_name,
    s.category AS service_category,
    a.paid
FROM appointments a
INNER JOIN users p  ON a.patient_id = p.id
INNER JOIN users pr ON a.professional_id = pr.id
INNER JOIN services s ON a.service_id = s.id
WHERE a.deleted_at IS NULL
  AND a.status NOT IN ('cancelled');


-- View: Próximos Agendamentos
CREATE VIEW vw_proximos AS
SELECT
    a.id,
    a.start_time,
    a.end_time,
    p.name AS patient_name,
    p.phone AS patient_phone,
    pr.name AS professional_name,
    s.name AS service_name,
    a.status,
    a.recurrence_type
FROM appointments a
INNER JOIN users p  ON a.patient_id = p.id
INNER JOIN users pr ON a.professional_id = pr.id
INNER JOIN services s ON a.service_id = s.id
WHERE a.deleted_at IS NULL
  AND a.start_time >= NOW()
  AND a.status IN ('scheduled', 'confirmed')
ORDER BY a.start_time
LIMIT 50;


-- View: Pagamentos Pendentes
CREATE VIEW vw_pendentes AS
SELECT
    a.id,
    a.start_time,
    p.name AS patient_name,
    p.email AS patient_email,
    p.phone AS patient_phone,
    pr.name AS professional_name,
    s.name AS service_name,
    s.category AS service_category,
    a.price,
    DATEDIFF(CURDATE(), DATE(a.start_time)) AS dias_em_aberto
FROM appointments a
INNER JOIN users p  ON a.patient_id = p.id
INNER JOIN users pr ON a.professional_id = pr.id
INNER JOIN services s ON a.service_id = s.id
WHERE a.deleted_at IS NULL
  AND a.paid = FALSE
  AND a.status = 'completed'
ORDER BY a.start_time;


-- View: Relatório Financeiro por Mês e Serviço
CREATE VIEW vw_financeiro AS
SELECT
    DATE_FORMAT(a.start_time, '%Y-%m') AS mes_ano,
    s.name AS service_name,
    s.category,
    COUNT(a.id) AS total_sessoes,
    SUM(CASE WHEN a.paid = TRUE THEN a.price ELSE 0 END) AS total_recebido,
    SUM(CASE WHEN a.paid = FALSE AND a.status = 'completed' THEN a.price ELSE 0 END) AS total_pendente,
    SUM(a.price) AS total_faturado,
    ROUND(AVG(a.price), 2) AS ticket_medio
FROM appointments a
INNER JOIN services s ON a.service_id = s.id
WHERE a.deleted_at IS NULL
  AND a.status IN ('completed', 'scheduled', 'confirmed')
GROUP BY DATE_FORMAT(a.start_time, '%Y-%m'), s.id
ORDER BY mes_ano DESC, total_faturado DESC;


-- View: Estatísticas por Serviço
CREATE VIEW vw_services_stats AS
SELECT
    s.id,
    s.name AS service_name,
    s.category,
    s.price AS service_price,
    s.active,
    COUNT(a.id) AS total_agendamentos,
    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS completados,
    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) AS cancelados,
    COUNT(CASE WHEN a.status = 'no_show' THEN 1 END) AS no_shows,
    SUM(CASE WHEN a.paid = TRUE THEN a.price ELSE 0 END) AS receita_total,
    ROUND(AVG(a.price), 2) AS ticket_medio
FROM services s
LEFT JOIN appointments a ON s.id = a.service_id AND a.deleted_at IS NULL
WHERE s.deleted_at IS NULL
GROUP BY s.id
ORDER BY total_agendamentos DESC;


-- View: Recorrências Ativas com detalhes
CREATE VIEW vw_recorrencias AS
SELECT
    rg.id AS recurrence_group_id,
    rg.type AS recurrence_type,
    rg.day_of_week,
    rg.start_hour,
    rg.start_date,
    rg.end_date,
    rg.active,

    p.name AS patient_name,
    p.phone AS patient_phone,
    pr.name AS professional_name,
    s.name AS service_name,

    COUNT(a.id) AS sessoes_geradas,
    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS sessoes_realizadas,
    COUNT(CASE WHEN a.start_time > NOW() AND a.status NOT IN ('cancelled') THEN 1 END) AS sessoes_futuras
FROM recurrence_groups rg
INNER JOIN users p  ON rg.patient_id = p.id
INNER JOIN users pr ON rg.professional_id = pr.id
INNER JOIN services s ON rg.service_id = s.id
LEFT JOIN appointments a ON a.recurrence_group_id = rg.id AND a.deleted_at IS NULL
GROUP BY rg.id;


-- =============================================
-- STORED PROCEDURES
-- =============================================

DELIMITER //

-- Procedure: Criar agendamento único
CREATE PROCEDURE sp_create_appointment(
    IN p_patient_id INT,
    IN p_professional_id INT,
    IN p_service_id INT,
    IN p_start_time DATETIME,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_price DECIMAL(10, 2);
    DECLARE v_duration INT;

    SELECT price, duration_minutes
    INTO v_price, v_duration
    FROM services
    WHERE id = p_service_id AND active = TRUE AND deleted_at IS NULL;

    IF v_price IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Serviço não encontrado ou inativo';
    END IF;

    INSERT INTO appointments (
        patient_id, professional_id, service_id,
        start_time, duration_minutes, price,
        recurrence_type, status, notes
    ) VALUES (
        p_patient_id, p_professional_id, p_service_id,
        p_start_time, v_duration, v_price,
        'unico', 'scheduled', p_notes
    );

    SELECT LAST_INSERT_ID() AS appointment_id;
END //


-- Procedure: Criar grupo de recorrência e gerar os agendamentos
-- Gera todas as sessões do período de uma vez
CREATE PROCEDURE sp_create_recurrence(
    IN p_patient_id INT,
    IN p_professional_id INT,
    IN p_service_id INT,
    IN p_type ENUM('semanal', 'quinzenal'),
    IN p_day_of_week TINYINT,      -- 0=Dom ... 6=Sáb
    IN p_start_hour TIME,
    IN p_start_date DATE,
    IN p_end_date DATE,            -- NULL para sem fim
    IN p_notes TEXT
)
BEGIN
    DECLARE v_price DECIMAL(10, 2);
    DECLARE v_duration INT;
    DECLARE v_group_id INT;
    DECLARE v_current_date DATE;
    DECLARE v_interval INT;
    DECLARE v_limit INT DEFAULT 0;  -- contador de segurança

    -- Busca dados do serviço
    SELECT price, duration_minutes
    INTO v_price, v_duration
    FROM services
    WHERE id = p_service_id AND active = TRUE AND deleted_at IS NULL;

    IF v_price IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Serviço não encontrado ou inativo';
    END IF;

    -- Define o intervalo em dias
    SET v_interval = IF(p_type = 'semanal', 7, 14);

    -- Cria o grupo de recorrência
    INSERT INTO recurrence_groups (
        patient_id, professional_id, service_id,
        type, day_of_week, start_hour,
        start_date, end_date, active
    ) VALUES (
        p_patient_id, p_professional_id, p_service_id,
        p_type, p_day_of_week, p_start_hour,
        p_start_date, p_end_date, TRUE
    );

    SET v_group_id = LAST_INSERT_ID();

    -- Encontra a primeira ocorrência a partir de p_start_date
    -- com o dia da semana correto
    SET v_current_date = p_start_date;
    WHILE DAYOFWEEK(v_current_date) - 1 != p_day_of_week DO
        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
    END WHILE;

    -- Gera os agendamentos
    -- Limite de 104 sessões (~2 anos de recorrência semanal) como proteção
    WHILE (p_end_date IS NULL OR v_current_date <= p_end_date) AND v_limit < 104 DO
        INSERT INTO appointments (
            patient_id, professional_id, service_id,
            recurrence_group_id, recurrence_type,
            start_time, duration_minutes, price,
            status, notes
        ) VALUES (
            p_patient_id, p_professional_id, p_service_id,
            v_group_id, p_type,
            TIMESTAMP(v_current_date, p_start_hour), v_duration, v_price,
            'scheduled', p_notes
        );

        SET v_current_date = DATE_ADD(v_current_date, INTERVAL v_interval DAY);
        SET v_limit = v_limit + 1;
    END WHILE;

    SELECT v_group_id AS recurrence_group_id, v_limit AS sessoes_criadas;
END //


-- Procedure: Cancelar recorrência a partir de uma data
-- Preserva sessões já realizadas, cancela apenas as futuras
CREATE PROCEDURE sp_cancel_recurrence(
    IN p_group_id INT,
    IN p_from_date DATE,
    IN p_reason TEXT
)
BEGIN
    -- Cancela appointments futuros do grupo
    UPDATE appointments
    SET status = 'cancelled',
        cancellation_reason = p_reason
    WHERE recurrence_group_id = p_group_id
      AND DATE(start_time) >= p_from_date
      AND status IN ('scheduled', 'confirmed')
      AND deleted_at IS NULL;

    -- Desativa o grupo de recorrência
    UPDATE recurrence_groups
    SET active = FALSE,
        end_date = DATE_SUB(p_from_date, INTERVAL 1 DAY)
    WHERE id = p_group_id;

    SELECT ROW_COUNT() AS sessoes_canceladas;
END //


-- Procedure: Registrar pagamento
CREATE PROCEDURE sp_register_payment(
    IN p_appointment_id INT,
    IN p_payment_method VARCHAR(50),
    IN p_payment_date DATE
)
BEGIN
    -- Só registra pagamento em sessões completadas ou confirmadas
    UPDATE appointments
    SET paid = TRUE,
        payment_method = p_payment_method,
        payment_date = p_payment_date
    WHERE id = p_appointment_id
      AND status IN ('completed', 'confirmed')
      AND deleted_at IS NULL;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Agendamento não encontrado, já deletado, ou com status inválido para pagamento';
    END IF;

    -- Retorna resumo do pagamento
    SELECT
        a.id,
        a.price,
        a.payment_method,
        s.name AS service_name,
        p.name AS patient_name
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.id
    INNER JOIN users p ON a.patient_id = p.id
    WHERE a.id = p_appointment_id;
END //


-- Procedure: Soft delete de usuário (verifica vínculos ativos)
CREATE PROCEDURE sp_delete_user(
    IN p_user_id INT
)
BEGIN
    DECLARE v_future_appointments INT;

    -- Verifica se há agendamentos futuros ativos
    SELECT COUNT(*) INTO v_future_appointments
    FROM appointments
    WHERE (patient_id = p_user_id OR professional_id = p_user_id)
      AND start_time > NOW()
      AND status IN ('scheduled', 'confirmed')
      AND deleted_at IS NULL;

    IF v_future_appointments > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Usuário possui agendamentos futuros. Cancele-os antes de desativar o cadastro.';
    END IF;

    -- Soft delete
    UPDATE users
    SET deleted_at = NOW(),
        active = FALSE
    WHERE id = p_user_id
      AND deleted_at IS NULL;

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Usuário não encontrado ou já desativado';
    END IF;

    SELECT p_user_id AS user_id, 'Usuário desativado com sucesso' AS message;
END //

DELIMITER ;


-- =============================================
-- DADOS DE EXEMPLO
-- =============================================

-- Serviços
INSERT INTO services (name, description, price, duration_minutes, category) VALUES
('Psicoterapia Individual',   'Sessão individual de psicoterapia cognitivo-comportamental', 150.00, 50,  'Individual'),
('Terapia de Casal',          'Sessão de terapia para casais',                              200.00, 60,  'Casal'),
('Terapia Familiar',          'Sessão de terapia familiar sistêmica',                       250.00, 90,  'Familiar'),
('Avaliação Psicológica',     'Avaliação psicológica completa com testes e relatórios',     300.00, 120, 'Avaliação'),
('Orientação Vocacional',     'Processo de orientação vocacional',                          180.00, 60,  'Orientação'),
('Psicoterapia Infantil',     'Sessão de psicoterapia infantil com ludoterapia',            160.00, 45,  'Individual'),
('Terapia de Grupo',          'Sessão em grupo para ansiedade e depressão',                  80.00, 90,  'Grupo'),
('Intervenção Psicopedagógica','Atendimento psicopedagógico para dificuldades de aprendizagem', 140.00, 50, 'Individual');

-- Profissionais (com e sem council_id)
INSERT INTO users (name, email, password, cpf, phone, role, professional_type, council_id, specialty, bio) VALUES
('Dr. Carlos Silva',  'carlos@clinica.com', '$2y$10$hash1', '111.111.111-11', '11-98765-4321', 'professional', 'Psicólogo',      'CRP 06/123456', 'Psicologia Clínica',          'Especialista em TCC com 10 anos de experiência'),
('Dra. Ana Costa',    'ana@clinica.com',    '$2y$10$hash2', '444.444.444-44', '11-97777-6666', 'professional', 'Psicólogo',      'CRP 06/789012', 'Terapia de Casal e Família',  'Especialista em terapia sistêmica familiar'),
('Beatriz Almeida',   'bia@clinica.com',    '$2y$10$hash7', '777.777.777-77', '11-95555-4444', 'professional', 'Psicopedagogo',  NULL,            'Dificuldades de Aprendizagem','Psicopedagoga com foco em crianças em idade escolar');

-- Pacientes
INSERT INTO users (name, email, password, cpf, phone, birthdate, role) VALUES
('João Paciente',   'joao@email.com',  '$2y$10$hash3', '222.222.222-22', '11-91234-5678', '1990-05-15', 'patient'),
('Maria Santos',    'maria@email.com', '$2y$10$hash4', '333.333.333-33', '11-99999-8888', '1985-08-20', 'patient'),
('Pedro Oliveira',  'pedro@email.com', '$2y$10$hash5', '555.555.555-55', '11-96666-7777', '1995-03-10', 'patient');

-- Admin
INSERT INTO users (name, email, password, role) VALUES
('Administrador', 'admin@clinica.com', '$2y$10$hash6', 'admin');

-- Agendamento único
CALL sp_create_appointment(4, 1, 1, '2026-02-20 14:00:00', 'Primeira sessão');

-- Recorrência semanal: João com Carlos, toda segunda-feira, das 10h
-- (day_of_week=1 = Segunda)
CALL sp_create_recurrence(4, 1, 1, 'semanal', 1, '10:00:00', '2026-02-16', '2026-06-30', 'Sessões semanais de TCC');

-- Recorrência quinzenal: Maria com Ana, toda quarta-feira, das 15h
CALL sp_create_recurrence(5, 2, 2, 'quinzenal', 3, '15:00:00', '2026-02-18', '2026-08-31', 'Terapia de casal quinzenal');


-- =============================================
-- CONSULTAS ÚTEIS
-- =============================================

-- Agenda de um mês para o calendário
-- SELECT * FROM vw_calendar
-- WHERE start_time BETWEEN '2026-02-01' AND '2026-02-28'
-- ORDER BY start_time;

-- Agenda de um profissional específico
-- SELECT * FROM vw_agenda
-- WHERE professional_id = 1
--   AND DATE(start_time) = CURDATE()
-- ORDER BY start_time;

-- Todas as recorrências ativas
-- SELECT * FROM vw_recorrencias WHERE active = TRUE;

-- Cancelar recorrência a partir de hoje
-- CALL sp_cancel_recurrence(1, CURDATE(), 'Paciente solicitou encerramento do tratamento');

-- Pagamentos pendentes
-- SELECT * FROM vw_pendentes;

-- Relatório financeiro do mês atual
-- SELECT * FROM vw_financeiro WHERE mes_ano = DATE_FORMAT(CURDATE(), '%Y-%m');

-- Buscar apenas profissionais ativos (com soft delete)
-- SELECT id, name, professional_type, council_id, specialty
-- FROM users
-- WHERE role = 'professional'
--   AND deleted_at IS NULL
--   AND active = TRUE;

-- Psicopedagogos (sem council_id)
-- SELECT * FROM users
-- WHERE role = 'professional'
--   AND professional_type = 'Psicopedagogo'
--   AND deleted_at IS NULL;