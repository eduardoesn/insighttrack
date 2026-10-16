<?php
declare(strict_types=1);

/** Consultas agregadas usadas pelos indicadores do painel. */
final class Dashboard
{
    public function __construct(private PDO $db) {}

    public function metrics(): array
    {
        $revenue = (float) $this->db->query('SELECT COALESCE(SUM(quantity * unit_price), 0) FROM sales')->fetchColumn();
        $count = (int) $this->db->query('SELECT COUNT(*) FROM sales')->fetchColumn();
        $top = $this->db->query('SELECT p.name FROM sales s JOIN products p ON p.id = s.product_id GROUP BY p.id, p.name ORDER BY SUM(s.quantity) DESC, p.name LIMIT 1')->fetch();
        return ['revenue' => $revenue, 'sales' => $count, 'average' => $count ? $revenue / $count : 0, 'top_product' => $top['name'] ?? '—'];
    }

    public function monthlyRevenue(): array
    {
        return $this->db->query("SELECT DATE_TRUNC('month', sold_at) AS month, SUM(quantity * unit_price) AS revenue FROM sales WHERE sold_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '5 months' GROUP BY DATE_TRUNC('month', sold_at) ORDER BY month")->fetchAll();
    }

    public function catalog(): array
    {
        return $this->db->query('SELECT COUNT(*) AS total, COALESCE(SUM(stock), 0) AS units FROM products')->fetch();
    }
}
