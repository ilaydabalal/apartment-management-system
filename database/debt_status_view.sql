-- Borç durumu görünümü
USE apartment_management;

CREATE OR REPLACE VIEW debt_status_view AS
SELECT 
    u.id as unit_id,
    a.name as apartment_name,
    u.unit_number,
    u.floor,
    u.resident_name,
    u.owner_name,
    u.resident_phone,
    u.resident_email,
    COALESCE(SUM(d.amount), 0) as total_dues,
    COALESCE(SUM(d.paid_amount), 0) as total_paid,
    COALESCE(SUM(d.amount - d.paid_amount), 0) as total_debt,
    COALESCE(SUM(CASE WHEN d.due_date < CURDATE() AND d.is_paid = 0 THEN 1 ELSE 0 END), 0) as overdue_count,
    MAX(p.payment_date) as last_payment_date,
    CASE 
        WHEN COALESCE(SUM(CASE WHEN d.due_date < CURDATE() AND d.is_paid = 0 THEN 1 ELSE 0 END), 0) >= 2 THEN 'Kritik'
        WHEN COALESCE(SUM(CASE WHEN d.due_date < CURDATE() AND d.is_paid = 0 THEN 1 ELSE 0 END), 0) >= 1 THEN 'Gecikmiş'
        ELSE 'Normal'
    END as debt_status
FROM apartment_units u
JOIN apartments a ON u.apartment_id = a.id
LEFT JOIN dues d ON u.id = d.unit_id AND d.status = 1
LEFT JOIN payments p ON d.id = p.due_id AND p.status = 1
WHERE u.status = 1 AND a.status = 1
GROUP BY u.id, a.name, u.unit_number, u.floor, u.resident_name, u.owner_name, u.resident_phone, u.resident_email; 