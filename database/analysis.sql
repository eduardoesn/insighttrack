-- 1. Qual foi o faturamento e o número de vendas por mês?
SELECT DATE_TRUNC('month', sold_at)::date AS month,
       SUM(quantity * unit_price) AS revenue,
       COUNT(*) AS sales_count
FROM sales
GROUP BY DATE_TRUNC('month', sold_at)
ORDER BY month;

-- 2. Quais produtos geram mais unidades vendidas e faturamento?
SELECT p.name,
       SUM(s.quantity) AS units_sold,
       SUM(s.quantity * s.unit_price) AS revenue
FROM sales s
JOIN products p ON p.id = s.product_id
GROUP BY p.id, p.name
ORDER BY units_sold DESC, revenue DESC;

-- 3. Quais clientes têm maior valor total comprado?
SELECT c.name,
       COUNT(s.id) AS purchases,
       SUM(s.quantity * s.unit_price) AS lifetime_revenue,
       AVG(s.quantity * s.unit_price) AS average_purchase
FROM customers c
JOIN sales s ON s.customer_id = c.id
GROUP BY c.id, c.name
ORDER BY lifetime_revenue DESC;

-- 4. Como o faturamento mensal evolui em relação ao mês anterior?
WITH monthly AS (
    SELECT DATE_TRUNC('month', sold_at)::date AS month,
           SUM(quantity * unit_price) AS revenue
    FROM sales
    GROUP BY DATE_TRUNC('month', sold_at)
)
SELECT month,
       revenue,
       LAG(revenue) OVER (ORDER BY month) AS previous_month_revenue,
       ROUND(100 * (revenue - LAG(revenue) OVER (ORDER BY month))
             / NULLIF(LAG(revenue) OVER (ORDER BY month), 0), 1) AS growth_percent
FROM monthly
ORDER BY month;

-- 5. Quais produtos estão com estoque baixo e quanto venderam nos últimos 30 dias?
SELECT p.name,
       p.stock,
       COALESCE(SUM(s.quantity) FILTER (WHERE s.sold_at >= NOW() - INTERVAL '30 days'), 0) AS units_sold_30d
FROM products p
LEFT JOIN sales s ON s.product_id = p.id
GROUP BY p.id, p.name, p.stock
HAVING p.stock <= 5
ORDER BY p.stock, p.name;
