<?php
declare(strict_types=1);

/** Consultas e persistência do catálogo de produtos. */
final class Product
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM products ORDER BY name')->fetchAll();
    }

    public function available(): array
    {
        return $this->db->query('SELECT id, name, price, stock FROM products WHERE stock > 0 ORDER BY name')->fetchAll();
    }

    public function create(array $data): void
    {
        $statement = $this->db->prepare('INSERT INTO products (name, price, stock) VALUES (:name, :price, :stock)');
        $statement->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $statement = $this->db->prepare('UPDATE products SET name = :name, price = :price, stock = :stock WHERE id = :id');
        $statement->execute($data + ['id' => $id]);
        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM products WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }
}
