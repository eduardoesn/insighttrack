<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Customer.php';
require_once __DIR__ . '/../app/models/Sale.php';

$db = database();
$token = bin2hex(random_bytes(6));
$productName = 'Integration test ' . $token;
$customerEmail = 'test-' . $token . '@example.invalid';
$productId = null;
$customerId = null;
$passed = 0;

// O nome aleatório evita colisões e os registros criados são removidos ao final.
function expect(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $passed++;
    echo "PASS {$message}\n";
}

try {
    $products = new Product($db);
    $customers = new Customer($db);
    $sales = new Sale($db);

    $products->create(['name' => $productName, 'price' => 19.9, 'stock' => 3]);
    $findProduct = $db->prepare('SELECT id FROM products WHERE name = :name');
    $findProduct->execute(['name' => $productName]);
    $productId = (int) $findProduct->fetchColumn();

    $customers->create(['name' => 'Integration Customer', 'email' => $customerEmail]);
    $findCustomer = $db->prepare('SELECT id FROM customers WHERE email = :email');
    $findCustomer->execute(['email' => $customerEmail]);
    $customerId = (int) $findCustomer->fetchColumn();

    $sales->create(['product_id' => $productId, 'customer_id' => $customerId, 'quantity' => 2], $customers);
    $stock = $db->prepare('SELECT stock FROM products WHERE id = :id');
    $stock->execute(['id' => $productId]);
    expect((int) $stock->fetchColumn() === 1, 'sale reduces stock within the successful transaction');

    $salePrice = $db->prepare('SELECT unit_price FROM sales WHERE product_id = :id');
    $salePrice->execute(['id' => $productId]);
    expect((float) $salePrice->fetchColumn() === 19.9, 'sale snapshots the catalog price');

    $products->update($productId, ['name' => $productName . ' editado', 'price' => 24.5, 'stock' => 1]);
    $currentProduct = $db->prepare('SELECT name, price FROM products WHERE id = :id');
    $currentProduct->execute(['id' => $productId]);
    $currentProduct = $currentProduct->fetch();
    expect($currentProduct['name'] === $productName . ' editado' && (float) $currentProduct['price'] === 24.5, 'product can be edited without changing the historical sale price');

    $customers->update($customerId, ['name' => 'Updated integration customer', 'email' => $customerEmail]);
    expect($customers->exists($customerId), 'customer record can be updated');

    $firstPage = $sales->paginate('', null, null, 1);
    expect(count($firstPage['items']) === 10 && $firstPage['pages'] >= 2, 'sales history returns ten records per page with a page count');

    $rejected = false;
    try {
        $sales->create(['product_id' => $productId, 'customer_id' => $customerId, 'quantity' => 2], $customers);
    } catch (DomainException) {
        $rejected = true;
    }
    $stock->execute(['id' => $productId]);
    expect($rejected && (int) $stock->fetchColumn() === 1, 'overselling is rejected and rolls back without changing stock');
} finally {
    if ($productId) {
        $deleteSales = $db->prepare('DELETE FROM sales WHERE product_id = :id');
        $deleteSales->execute(['id' => $productId]);
        $deleteProduct = $db->prepare('DELETE FROM products WHERE id = :id');
        $deleteProduct->execute(['id' => $productId]);
    }
    if ($customerId) {
        $deleteCustomer = $db->prepare('DELETE FROM customers WHERE id = :id');
        $deleteCustomer->execute(['id' => $customerId]);
    }
}

echo "{$passed} integration checks passed\n";
