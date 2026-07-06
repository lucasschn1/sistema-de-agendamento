-- =============================================
-- SISTEMA DE AGENDAMENTO 
-- Com Classe Service (Serviços de Psicologia)
-- =============================================

CREATE DATABASE IF NOT EXISTS agenda_simples;
USE agenda_simples;

-- =============================================
-- TABELA: users (Usuários do Sistema)
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
    role ENUM('admin', 'patient', 'psychologist') NOT NULL DEFAULT 'patient',
    
    -- Campos específicos de Psicólogo (NULL para pacientes)
    crp VARCHAR(20),
    specialty VARCHAR(100),
    bio TEXT,
    
    -- Controle
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_email (email),
    INDEX idx_cpf (cpf),
    INDEX idx_role (role),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Usuários: pacientes, psicólogos e admins';

-- =============================================
-- TABELA: services (Catálogo de Serviços) 
-- =============================================
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Informações do Serviço
    name VARCHAR(100) NOT NULL COMMENT 'Ex: Psicoterapia Individual, Terapia de Casal',
    description TEXT COMMENT 'Descrição detalhada do serviço',
    
    -- Precificação
    price DECIMAL(10, 2) NOT NULL COMMENT 'Preço base do serviço',
    
    -- Duração
    duration_minutes INT DEFAULT 50 COMMENT 'Duração padrão em minutos',
    
    -- Categorização
    category VARCHAR(50) COMMENT 'Individual, Casal, Familiar, Grupo, Avaliação',
    
    -- Controle
    active BOOLEAN DEFAULT TRUE COMMENT 'Serviço disponível para agendamento',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_active (active),
    INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Catálogo de serviços oferecidos pela clínica';

-- =============================================
-- TABELA: appointments (Agendamentos)
-- =============================================
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Relacionamentos
    patient_id INT NOT NULL COMMENT 'Quem será atendido',
    psychologist_id INT NOT NULL COMMENT 'Quem vai atender',
    service_id INT NOT NULL COMMENT 'Qual serviço será prestado', -- ⭐ NOVO
    
    -- Data e Hora
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    duration_minutes INT DEFAULT 50,
    
    -- Financeiro
    price DECIMAL(10, 2) NOT NULL COMMENT 'Valor cobrado neste agendamento específico',
    paid BOOLEAN DEFAULT FALSE,
    payment_method VARCHAR(50) COMMENT 'PIX, Dinheiro, Cartão, etc',
    payment_date DATE,
    
    -- Status 
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled') DEFAULT 'scheduled',
    
    -- Recorrência (controle no código PHP)
    recurrence_group VARCHAR(50) NULL COMMENT 'ID para agrupar recorrências',
    recurrence_type ENUM('unico', 'semanal', 'quinzenal') DEFAULT 'unico',
    
    -- Observações
    notes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Chaves Estrangeiras
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (psychologist_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id), -- ⭐ NOVO
    
    -- Índices para Performance
    INDEX idx_patient_id (patient_id),
    INDEX idx_psychologist_id (psychologist_id),
    INDEX idx_service_id (service_id), -- ⭐ NOVO
    INDEX idx_psycho_time (psychologist_id, start_time),
    INDEX idx_patient_time (patient_id, start_time),
    INDEX idx_status (status),
    INDEX idx_recurrence (recurrence_group),
    INDEX idx_start_time (start_time),
    INDEX idx_payment (paid, payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Agendamentos e histórico financeiro';

-- =============================================
-- TRIGGER: Calcular end_time automaticamente
-- =============================================
DELIMITER //
CREATE TRIGGER trg_calc_end_time
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    IF NEW.end_time IS NULL OR NEW.end_time = NEW.start_time THEN
        SET NEW.end_time = DATE_ADD(NEW.start_time, INTERVAL NEW.duration_minutes MINUTE);
    END IF;
END //
DELIMITER ;

-- =============================================
-- VIEWS (Consultas Facilitadas)
-- =============================================

-- View: Agenda Completa com Informações do Serviço
CREATE VIEW vw_agenda AS
SELECT 
    a.id,
    a.start_time,
    a.end_time,
    a.duration_minutes,
    
    -- Paciente
    a.patient_id,
    p.name AS patient_name,
    p.email AS patient_email,
    p.phone AS patient_phone,
    
    -- Psicólogo
    a.psychologist_id,
    ps.name AS psychologist_name,
    ps.specialty,
    ps.crp,
    
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
    
    -- Status e outros
    a.status,
    a.recurrence_type,
    a.recurrence_group,
    a.notes,
    a.created_at
FROM appointments a
INNER JOIN users p ON a.patient_id = p.id
INNER JOIN users ps ON a.psychologist_id = ps.id
INNER JOIN services s ON a.service_id = s.id; -- NOVO

-- View: Serviços Mais Agendados
CREATE VIEW vw_services_stats AS
SELECT 
    s.id,
    s.name AS service_name,
    s.category,
    s.price AS service_price,
    COUNT(a.id) AS total_appointments,
    COUNT(CASE WHEN a.status = 'completed' THEN 1 END) AS completed,
    COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) AS cancelled,
    SUM(CASE WHEN a.paid THEN a.price ELSE 0 END) AS total_revenue,
    AVG(a.price) AS avg_price_charged,
    s.active
FROM services s
LEFT JOIN appointments a ON s.id = a.service_id
GROUP BY s.id
ORDER BY total_appointments DESC;

-- View: Pagamentos Pendentes (com serviço)
CREATE VIEW vw_pendentes AS
SELECT 
    a.id,
    a.start_time,
    p.name AS patient_name,
    p.email AS patient_email,
    p.phone AS patient_phone,
    ps.name AS psychologist_name,
    s.name AS service_name, -- ⭐ NOVO
    s.category AS service_category, -- ⭐ NOVO
    a.price,
    DATEDIFF(CURDATE(), DATE(a.start_time)) AS dias_atraso
FROM appointments a
INNER JOIN users p ON a.patient_id = p.id
INNER JOIN users ps ON a.psychologist_id = ps.id
INNER JOIN services s ON a.service_id = s.id -- ⭐ NOVO
WHERE a.paid = FALSE
  AND a.status = 'completed'
ORDER BY a.start_time;

-- View: Próximos Agendamentos (com serviço)
CREATE VIEW vw_proximos AS
SELECT 
    a.id,
    a.start_time,
    a.end_time,
    p.name AS patient_name,
    ps.name AS psychologist_name,
    s.name AS service_name, 
    s.duration_minutes,
    a.status
FROM appointments a
INNER JOIN users p ON a.patient_id = p.id
INNER JOIN users ps ON a.psychologist_id = ps.id
INNER JOIN services s ON a.service_id = s.id 
WHERE a.start_time >= NOW()
  AND a.status IN ('scheduled', 'confirmed')
ORDER BY a.start_time
LIMIT 20;

-- View: Relatório Financeiro por Serviço
CREATE VIEW vw_financial_by_service AS
SELECT 
    DATE_FORMAT(a.start_time, '%Y-%m') AS mes_ano,
    s.name AS service_name,
    s.category,
    COUNT(a.id) AS total_appointments,
    SUM(CASE WHEN a.paid THEN a.price ELSE 0 END) AS total_received,
    SUM(CASE WHEN NOT a.paid AND a.status = 'completed' THEN a.price ELSE 0 END) AS total_pending,
    SUM(a.price) AS total_charged,
    ROUND(AVG(a.price), 2) AS avg_price
FROM appointments a
INNER JOIN services s ON a.service_id = s.id
WHERE a.status IN ('completed', 'scheduled', 'confirmed')
GROUP BY DATE_FORMAT(a.start_time, '%Y-%m'), s.id
ORDER BY mes_ano DESC, total_charged DESC;

-- =============================================
-- DADOS DE EXEMPLO
-- =============================================

-- 1. Serviços da Clínica 
INSERT INTO services (name, description, price, duration_minutes, category, active) VALUES
('Psicoterapia Individual', 'Sessão individual de psicoterapia cognitivo-comportamental', 150.00, 50, 'Individual', TRUE),
('Terapia de Casal', 'Sessão de terapia para casais com dificuldades de relacionamento', 200.00, 60, 'Casal', TRUE),
('Terapia Familiar', 'Sessão de terapia familiar sistêmica', 250.00, 90, 'Familiar', TRUE),
('Avaliação Psicológica', 'Avaliação psicológica completa com testes e relatórios', 300.00, 120, 'Avaliação', TRUE),
('Orientação Vocacional', 'Processo de orientação vocacional para adolescentes e jovens', 180.00, 60, 'Orientação', TRUE),
('Psicoterapia Infantil', 'Sessão de psicoterapia para crianças utilizando ludoterapia', 160.00, 45, 'Individual', TRUE),
('Terapia de Grupo', 'Sessão em grupo para ansiedade e depressão', 80.00, 90, 'Grupo', TRUE);

-- 2. Psicólogos
INSERT INTO users (name, email, password, cpf, phone, role, crp, specialty, bio) VALUES
('Dr. Carlos Silva', 'carlos@clinica.com', '$2y$10$hash_example_1', '111.111.111-11', '11-98765-4321', 'psychologist', 'CRP 06/123456', 'Psicologia Clínica', 'Especialista em TCC com 10 anos de experiência'),
('Dra. Ana Costa', 'ana@clinica.com', '$2y$10$hash_example_2', '444.444.444-44', '11-97777-6666', 'psychologist', 'CRP 06/789012', 'Terapia de Casal e Família', 'Especialista em terapia sistêmica familiar');

-- 3. Pacientes
INSERT INTO users (name, email, password, cpf, phone, birthdate, role) VALUES
('João Paciente', 'joao@email.com', '$2y$10$hash_example_3', '222.222.222-22', '11-91234-5678', '1990-05-15', 'patient'),
('Maria Santos', 'maria@email.com', '$2y$10$hash_example_4', '333.333.333-33', '11-99999-8888', '1985-08-20', 'patient'),
('Pedro Oliveira', 'pedro@email.com', '$2y$10$hash_example_5', '555.555.555-55', '11-96666-7777', '1995-03-10', 'patient');

-- 4. Admin
INSERT INTO users (name, email, password, role) VALUES
('Administrador', 'admin@clinica.com', '$2y$10$hash_example_6', 'admin');

-- 5. Agendamentos de Exemplo (agora com service_id)
INSERT INTO appointments (patient_id, psychologist_id, service_id, start_time, duration_minutes, price, status) VALUES
(3, 1, 1, '2026-02-15 14:00:00', 50, 150.00, 'scheduled'),      -- João - Psicoterapia Individual
(4, 1, 1, '2026-02-15 15:00:00', 50, 150.00, 'confirmed'),    -- Maria - Psicoterapia Individual
(5, 2, 2, '2026-02-16 16:00:00', 60, 200.00, 'scheduled'),      -- Pedro - Terapia de Casal
(3, 1, 6, '2026-02-17 10:00:00', 45, 160.00, 'scheduled'),      -- João - Psicoterapia Infantil
(4, 2, 3, '2026-02-18 14:00:00', 90, 250.00, 'confirmed');    -- Maria - Terapia Familiar
-- =============================================
-- STORED PROCEDURES (Opcional)
-- =============================================

-- Procedure: Criar agendamento com validação de serviço
DELIMITER //
CREATE PROCEDURE sp_create_appointment(
    IN p_patient_id INT,
    IN p_psychologist_id INT,
    IN p_service_id INT,
    IN p_start_time DATETIME,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_service_price DECIMAL(10, 2);
    DECLARE v_duration INT;
    
    -- Busca informações do serviço
    SELECT price, duration_minutes 
    INTO v_service_price, v_duration
    FROM services
    WHERE id = p_service_id AND active = TRUE;
    
    -- Valida se serviço existe e está ativo
    IF v_service_price IS NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Serviço não encontrado ou inativo';
    END IF;
    
    -- Cria o agendamento
    INSERT INTO appointments (
        patient_id,
        psychologist_id,
        service_id,
        start_time,
        duration_minutes,
        price,
        status,
        notes
    ) VALUES (
        p_patient_id,
        p_psychologist_id,
        p_service_id,
        p_start_time,
        v_duration,
        v_service_price,
        'scheduled',
        p_notes
    );
    
    SELECT LAST_INSERT_ID() AS appointment_id;
END //
DELIMITER ;

-- Procedure: Registrar pagamento
DELIMITER //
CREATE PROCEDURE sp_register_payment(
    IN p_appointment_id INT,
    IN p_payment_method VARCHAR(50),
    IN p_payment_date DATE
)
BEGIN
    UPDATE appointments
    SET paid = TRUE,
        payment_method = p_payment_method,
        payment_date = p_payment_date,
        status = IF(status = 'scheduled', 'completed', status)
    WHERE id = p_appointment_id;
    
    SELECT 
        a.id,
        a.price,
        s.name AS service_name,
        p.name AS patient_name
    FROM appointments a
    INNER JOIN services s ON a.service_id = s.id
    INNER JOIN users p ON a.patient_id = p.id
    WHERE a.id = p_appointment_id;
END //
DELIMITER ;

-- =============================================
-- CONSULTAS ÚTEIS
-- =============================================

-- 1. Listar todos os serviços ativos
SELECT * FROM services WHERE active = TRUE ORDER BY category, name;

-- 2. Ver agenda completa com serviços
SELECT * FROM vw_agenda WHERE start_time >= CURDATE() ORDER BY start_time;

-- 3. Estatísticas por serviço
SELECT * FROM vw_services_stats;

-- 4. Próximos agendamentos
SELECT * FROM vw_proximos;

-- 5. Pagamentos pendentes com tipo de serviço
SELECT * FROM vw_pendentes;

-- 6. Relatório financeiro por serviço no mês atual
SELECT * FROM vw_financial_by_service
WHERE mes_ano = DATE_FORMAT(CURDATE(), '%Y-%m');

-- 7. Serviços mais rentáveis
SELECT 
    service_name,
    category,
    total_revenue,
    total_appointments,
    ROUND(total_revenue / total_appointments, 2) AS revenue_per_appointment
FROM vw_services_stats
WHERE total_appointments > 0
ORDER BY total_revenue DESC;

-- 8. Agenda de um psicólogo com serviços
SELECT 
    start_time,
    patient_name,
    service_name,
    price,
    status
FROM vw_agenda
WHERE psychologist_id = 1
  AND DATE(start_time) = CURDATE()
ORDER BY start_time;

-- 9. Histórico de agendamentos de um paciente por tipo de serviço
SELECT 
    service_name,
    COUNT(*) AS total,
    SUM(CASE WHEN paid THEN price ELSE 0 END) AS total_paid
FROM vw_agenda
WHERE patient_id = 3
GROUP BY service_id, service_name;

-- 10. Verificar disponibilidade de um serviço
SELECT 
    s.name,
    s.price,
    s.duration_minutes,
    COUNT(a.id) AS agendamentos_mes_atual
FROM services s
LEFT JOIN appointments a ON s.id = a.service_id 
    AND MONTH(a.start_time) = MONTH(CURDATE())
    AND YEAR(a.start_time) = YEAR(CURDATE())
WHERE s.active = TRUE
GROUP BY s.id;

-- 11. Total a receber por serviço
SELECT 
    s.name AS service_name,
    COUNT(a.id) AS pendentes,
    SUM(a.price) AS total_pendente
FROM appointments a
INNER JOIN services s ON a.service_id = s.id
WHERE a.paid = FALSE
  AND a.status = 'completed'
GROUP BY s.id
ORDER BY total_pendente DESC;

-- =============================================
-- COMANDOS DE MANUTENÇÃO
-- =============================================

-- Ativar um serviço
-- UPDATE services SET active = TRUE WHERE id = 1;

-- Desativar um serviço
-- UPDATE services SET active = FALSE WHERE id = 1;

-- Atualizar preço de um serviço
-- UPDATE services SET price = 160.00 WHERE id = 1;

-- Adicionar novo serviço
-- INSERT INTO services (name, description, price, duration_minutes, category)
-- VALUES ('Novo Serviço', 'Descrição', 100.00, 50, 'Individual');