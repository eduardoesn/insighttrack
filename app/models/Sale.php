<?php
declare(strict_types=1);

/** Consultas e persistência das vendas, incluindo filtros e paginação. */
final class Sale
{
    public function __construct(private PDO $db) {}

    public function create(array $data, Customer $customers): void
    {
        $this->db->beginTransaction();
        try {
            // O lock evita que duas solicitações vendam simultaneamente o mesmo estoque disponível.
            $find = $this->db->prepare('SELECT id, price, stock FROM products WHERE id = :id FOR UPDATE');
            $find->execute(['id' => $data['product_id']]);
            $product = $find->fetch();
            if (!$product || (int) $product['stock'] < $data['quantity']) {
                throw new DomainException('Produto não encontrado ou estoque insuficiente.');
            }
            if ($data['customer_id'] !== null && !$customers->exists((int) $data['customer_id'])) {
                throw new DomainException('Cliente selecionado não existe.');
            }
            // Guarda o preço vigente na venda para preservar o histórico do valor pago.
            $insert = $this->db->prepare('INSERT INTO sales (customer_id, product_id, quantity, unit_price) VALUES (:customer_id, :product_id, :quantity, :unit_price)');
            $insert->execute($data + ['unit_price' => $product['price']]);
            $update = $this->db->prepare('UPDATE products SET stock = stock - :quantity WHERE id = :id');
            $update->execute(['quantity' => $data['quantity'], 'id' => $data['product_id']]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function paginate(string $search, ?string $from, ?string $to, int $page, int $perPage = 10): array
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(c.name ILIKE :customer_query OR p.name ILIKE :product_query)';
            $params['customer_query'] = '%' . $search . '%';
            $params['product_query'] = '%' . $search . '%';
        }
        if ($from !== null) {
            $where[] = 's.sold_at >= CAST(:date_from AS DATE)';
            $params['date_from'] = $from;
        }
        if ($to !== null) {
            $where[] = "s.sold_at < CAST(:date_to AS DATE) + INTERVAL '1 day'";
            $params['date_to'] = $to;
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        // A contagem separada permite mostrar total e páginas sem carregar todo o histórico.
        $count = $this->db->prepare('SELECT COUNT(*) FROM sales s LEFT JOIN customers c ON c.id = s.customer_id JOIN products p ON p.id = s.product_id' . $clause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->db->prepare('SELECT s.id, s.sold_at, s.quantity, s.unit_price, c.name AS customer_name, p.name AS product_name FROM sales s LEFT JOIN customers c ON c.id = s.customer_id JOIN products p ON p.id = s.product_id' . $clause . ' ORDER BY s.sold_at DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        return ['items' => $statement->fetchAll(), 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public function recent(int $limit = 6): array
    {
        $statement = $this->db->prepare('SELECT s.id, s.sold_at, s.quantity, s.unit_price, c.name AS customer_name, p.name AS product_name FROM sales s LEFT JOIN customers c ON c.id = s.customer_id JOIN products p ON p.id = s.product_id ORDER BY s.sold_at DESC LIMIT :limit');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}
