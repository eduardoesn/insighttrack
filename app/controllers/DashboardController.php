<?php
declare(strict_types=1);

/** Monta o conjunto de dados que a página do painel precisa apresentar. */
final class DashboardController
{
    public function __construct(private Dashboard $dashboard, private Sale $sales) {}

    public function index(): array
    {
        return [
            'metrics' => $this->dashboard->metrics(),
            'monthly' => $this->dashboard->monthlyRevenue(),
            'catalog' => $this->dashboard->catalog(),
            'recent_sales' => $this->sales->recent(),
        ];
    }
}
