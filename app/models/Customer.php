<?php
declare(strict_types=1);

/** Consultas e persistência dos clientes. */
final class Customer
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT * FROM customers ORDER BY name')->fetchAll();
    }

    public function selectOptions(): array
    {
        return $this->db->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
    }

    public function exists(int $id): bool
    {
        $statement = $this->db->prepare('SELECT 1 FROM customers WHERE id = :id');
        $statement->execute(['id' => $id]);
        return (bool) $statement->fetchColumn();
    }

    public function create(array $data): void
    {
        $statement = $this->db->prepare('INSERT INTO customers (name, email) VALUES (:name, :email)');
        $statement->execute($data);
    }

    public function update(int $id, array $data): bool
    {
        $statement = $this->db->prepare('UPDATE customers SET name = :name, email = :email WHERE id = :id');
        $statement->execute($data + ['id' => $id]);
        return $statement->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $statement = $this->db->prepare('DELETE FROM customers WHERE id = :id');
        $statement->execute(['id' => $id]);
        return $statement->rowCount() > 0;
    }
}
