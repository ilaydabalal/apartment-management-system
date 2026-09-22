-- Mali Raporlar View'ı

CREATE OR REPLACE VIEW financial_report_view AS
SELECT 
    YEAR(COALESCE(i.income_date, e.expense_date)) as report_year,
    MONTH(COALESCE(i.income_date, e.expense_date)) as report_month,
    DATE_FORMAT(COALESCE(i.income_date, e.expense_date), '%Y-%m') as period,
    COALESCE(SUM(i.amount), 0) as total_income,
    COALESCE(SUM(e.amount), 0) as total_expense,
    COALESCE(SUM(i.amount), 0) - COALESCE(SUM(e.amount), 0) as net_income,
    COUNT(DISTINCT i.id) as income_count,
    COUNT(DISTINCT e.id) as expense_count
FROM (
    SELECT income_date, amount, id FROM incomes WHERE status = 1
    UNION ALL
    SELECT expense_date, 0, NULL FROM expenses WHERE status = 1
) as dates
LEFT JOIN incomes i ON dates.income_date = i.income_date AND i.status = 1
LEFT JOIN expenses e ON dates.expense_date = e.expense_date AND e.status = 1
WHERE dates.income_date IS NOT NULL OR dates.expense_date IS NOT NULL
GROUP BY YEAR(COALESCE(i.income_date, e.expense_date)), MONTH(COALESCE(i.income_date, e.expense_date))
ORDER BY report_year DESC, report_month DESC;

-- Gelir Dağılımı View'ı
CREATE OR REPLACE VIEW income_distribution_view AS
SELECT 
    ic.name as category_name,
    ic.id as category_id,
    COUNT(i.id) as transaction_count,
    SUM(i.amount) as total_amount,
    ROUND((SUM(i.amount) / (SELECT SUM(amount) FROM incomes WHERE status = 1)) * 100, 2) as percentage
FROM income_categories ic
LEFT JOIN incomes i ON ic.id = i.category_id AND i.status = 1
WHERE ic.status = 1
GROUP BY ic.id, ic.name
ORDER BY total_amount DESC;

-- Gider Dağılımı View'ı
CREATE OR REPLACE VIEW expense_distribution_view AS
SELECT 
    ec.name as category_name,
    ec.id as category_id,
    COUNT(e.id) as transaction_count,
    SUM(e.amount) as total_amount,
    ROUND((SUM(e.amount) / (SELECT SUM(amount) FROM expenses WHERE status = 1)) * 100, 2) as percentage
FROM expense_categories ec
LEFT JOIN expenses e ON ec.id = e.category_id AND e.status = 1
WHERE ec.status = 1
GROUP BY ec.id, ec.name
ORDER BY total_amount DESC; 