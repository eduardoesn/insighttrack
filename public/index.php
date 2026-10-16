<?php
declare(strict_types=1);

session_start();
// O front controller carrega as dependências pequenas sem exigir um framework/autoloader.
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/validation/InputValidator.php';
require_once __DIR__ . '/../app/models/Product.php';
require_once __DIR__ . '/../app/models/Customer.php';
require_once __DIR__ . '/../app/models/Sale.php';
require_once __DIR__ . '/../app/models/Dashboard.php';
require_once __DIR__ . '/../app/controllers/ProductController.php';
require_once __DIR__ . '/../app/controllers/CustomerController.php';
require_once __DIR__ . '/../app/controllers/SaleController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function shortDate(mixed $value): string
{
    $date = new DateTimeImmutable((string) $value);
    $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $date->format('d') . ' ' . $months[(int) $date->format('n') - 1] . ' ' . $date->format('Y');
}

function shortMonth(mixed $value): string
{
    $date = new DateTimeImmutable((string) $value);
    $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    return $months[(int) $date->format('n') - 1];
}

function redirect(string $page, string $message): never
{
    $_SESSION['flash'] = $message;
    header('Location: /?page=' . rawurlencode($page));
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Falha de banco é registrada no log, mas a página mostra uma mensagem curta ao usuário.
$db = null;
$dbError = null;
try {
    $db = database();
} catch (Throwable $exception) {
    error_log('InsightTrack database connection failed: ' . $exception->getMessage());
    $dbError = 'Banco indisponível. Inicie os serviços com docker compose up --build.';
}

$page = (string) ($_GET['page'] ?? 'dashboard');
if (!in_array($page, ['dashboard', 'products', 'customers', 'sales'], true)) {
    $page = 'dashboard';
}
$error = null;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$productsModel = $db instanceof PDO ? new Product($db) : null;
$customersModel = $db instanceof PDO ? new Customer($db) : null;
$salesModel = $db instanceof PDO ? new Sale($db) : null;
$dashboardModel = $db instanceof PDO ? new Dashboard($db) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db instanceof PDO) {
    try {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.');
        }
        $action = (string) ($_POST['action'] ?? '');
        // Após uma gravação bem-sucedida, redirect() aplica o padrão POST/Redirect/GET.
        if (str_starts_with($action, 'product.')) {
            redirect('products', (new ProductController($productsModel))->handle($action, $_POST));
        }
        if (str_starts_with($action, 'customer.')) {
            redirect('customers', (new CustomerController($customersModel))->handle($action, $_POST));
        }
        if ($action === 'sale.create') {
            redirect('sales', (new SaleController($salesModel, $customersModel))->create($_POST));
        }
        throw new InvalidArgumentException('Ação não reconhecida.');
    } catch (Throwable $exception) {
        if ($exception instanceof PDOException) {
            $error = str_contains($exception->getMessage(), 'customers_email_key') ? 'Este e-mail já está cadastrado.'
                : (str_contains($exception->getMessage(), 'sales_product_id_fkey') ? 'Este produto possui vendas e não pode ser removido.'
                : 'Não foi possível salvar os dados. Confira os campos e tente novamente.');
        } else {
            $error = $exception->getMessage();
        }
    }
}

$metrics = ['revenue' => 0, 'sales' => 0, 'average' => 0, 'top_product' => '—'];
$monthly = [];
$latestSales = [];
$products = [];
$customers = [];
$records = [];
$filter = trim((string) ($_GET['q'] ?? ''));
$salesPage = ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$fromDate = (string) ($_GET['from'] ?? '');
$toDate = (string) ($_GET['to'] ?? '');
// Cada página busca somente os dados necessários para sua própria visualização.
if ($db instanceof PDO) {
    try {
        if ($page === 'dashboard') {
            $dashboard = (new DashboardController($dashboardModel, $salesModel))->index();
            $metrics = $dashboard['metrics'];
            $monthly = $dashboard['monthly'];
            $latestSales = $dashboard['recent_sales'];
            $catalogData = $dashboard['catalog'];
        } elseif ($page === 'products') {
            $products = $productsModel->all();
        } elseif ($page === 'customers') {
            $customers = $customersModel->all();
        } elseif ($page === 'sales') {
            $products = $productsModel->available();
            $customers = $customersModel->selectOptions();
            $salesPage = (new SaleController($salesModel, $customersModel))->list($_GET);
            $records = $salesPage['items'];
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$nav = ['dashboard' => 'Visão geral', 'sales' => 'Vendas', 'products' => 'Produtos', 'customers' => 'Clientes'];
$titles = ['dashboard' => 'Visão geral', 'sales' => 'Vendas', 'products' => 'Produtos', 'customers' => 'Clientes'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f4f6f8">
    <title><?= e($titles[$page]) ?> · InsightTrack</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/assets/forms.css">
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="/?page=dashboard"><span class="brand-icon">i</span><span>insight<span class="brand-light">track</span></span></a>
    <p class="nav-caption">ESPAÇO DE TRABALHO</p>
    <nav aria-label="Navegação principal">
        <?php foreach ($nav as $key => $label): ?>
            <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="/?page=<?= e($key) ?>"><span class="nav-icon" aria-hidden="true"><?= ['dashboard' => '◫', 'sales' => '↗', 'products' => '▤', 'customers' => '♙'][$key] ?></span><?= e($label) ?><?= $key === 'sales' ? '<span class="nav-arrow">›</span>' : '' ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom"><span class="avatar">IT</span><span><strong>InsightTrack</strong><small>Plano de demonstração</small></span><span class="more">···</span></div>
</aside>
<main class="main">
    <header class="topbar"><div class="crumb">Workspace <span>/</span> <strong><?= e($titles[$page]) ?></strong></div><div class="top-actions"><span class="today"><?= date('d/m/Y') ?></span><span class="avatar small">IT</span></div></header>
    <div class="content">
        <?php if ($flash): ?><div class="alert success" role="status"><?= e($flash) ?></div><?php endif; ?>
        <?php if ($error || $dbError): ?><div class="alert error" role="alert"><?= e($error ?? $dbError) ?></div><?php endif; ?>
        <?php if ($page === 'dashboard'): ?>
            <div class="page-heading"><div><p class="eyebrow">RESUMO DO NEGÓCIO</p><h1>Visão geral</h1><p class="subheading">Aqui está o que está acontecendo com suas vendas.</p></div><a class="button primary" href="/?page=sales#new-sale"><span>＋</span> Registrar venda</a></div>
            <section class="metrics" aria-label="Indicadores principais">
                <article class="metric-card"><div class="metric-top"><span>Faturamento total</span><span class="metric-symbol green">↗</span></div><strong><?= money($metrics['revenue']) ?></strong><small>Valor acumulado em vendas</small></article>
                <article class="metric-card"><div class="metric-top"><span>Vendas realizadas</span><span class="metric-symbol blue">▣</span></div><strong><?= number_format($metrics['sales'], 0, ',', '.') ?></strong><small>Transações registradas</small></article>
                <article class="metric-card"><div class="metric-top"><span>Ticket médio</span><span class="metric-symbol amber">◉</span></div><strong><?= money($metrics['average']) ?></strong><small>Faturamento por venda</small></article>
                <article class="metric-card"><div class="metric-top"><span>Mais vendido</span><span class="metric-symbol violet">☆</span></div><strong class="top-product"><?= e($metrics['top_product']) ?></strong><small>Produto por unidades vendidas</small></article>
            </section>
            <section class="dashboard-grid">
                <article class="panel chart-panel"><div class="panel-heading"><div><h2>Faturamento por mês</h2><p>Desempenho dos últimos seis meses</p></div><span class="period">6 meses⌄</span></div>
                    <?php $maxRevenue = max(array_map(static fn($row) => (float) $row['revenue'], $monthly) ?: [1]); ?>
                    <div class="chart" role="img" aria-label="Gráfico de faturamento por mês">
                        <?php foreach ($monthly as $row): $height = max(5, (int) round(((float) $row['revenue'] / $maxRevenue) * 100)); ?>
                            <div class="chart-column"><span class="bar-value"><?= e(number_format((float) $row['revenue'] / 1000, 1, ',', '.')) ?>k</span><div class="bar-track"><span class="bar" style="height:<?= $height ?>%"></span></div><span class="bar-label"><?= e(shortMonth($row['month'])) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </article>
                <article class="panel insight-panel"><div class="panel-heading"><div><h2>Seu catálogo</h2><p>Visão rápida do estoque atual</p></div><a class="text-link" href="/?page=products">Ver produtos →</a></div>
                    <?php $catalog = $catalogData ?? ['total' => 0, 'units' => 0]; ?>
                    <div class="catalog-number"><?= e($catalog['total']) ?><small>produtos ativos</small></div><div class="stock-line"><span>Unidades em estoque</span><strong><?= e($catalog['units']) ?></strong></div>
                    <a class="button secondary full" href="/?page=products">Gerenciar catálogo</a>
                </article>
            </section>
            <section class="panel table-panel"><div class="panel-heading"><div><h2>Vendas recentes</h2><p>Últimas transações registradas</p></div><a class="text-link" href="/?page=sales">Ver todas →</a></div>
                <?php if ($latestSales): ?><div class="table-wrap"><table><thead><tr><th>PRODUTO</th><th>CLIENTE</th><th>DATA</th><th>VALOR</th><th>STATUS</th></tr></thead><tbody><?php foreach ($latestSales as $sale): ?><tr><td><span class="product-initial"><?= e(mb_strtoupper(mb_substr($sale['product_name'], 0, 1))) ?></span><strong class="table-primary"><?= e($sale['product_name']) ?></strong></td><td><?= e($sale['customer_name'] ?? 'Cliente avulso') ?></td><td><?= e(shortDate($sale['sold_at'])) ?></td><td><strong><?= money((float) $sale['unit_price'] * (int) $sale['quantity']) ?></strong></td><td><span class="status"><i></i> Concluída</span></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">Ainda não há vendas registradas.</p><?php endif; ?>
            </section>
        <?php elseif ($page === 'products'): ?>
            <div class="page-heading"><div><p class="eyebrow">CATÁLOGO</p><h1>Produtos</h1><p class="subheading">Gerencie os itens disponíveis para venda.</p></div><a class="button primary" href="#new-product">＋ Novo produto</a></div>
            <section class="panel form-panel" id="new-product"><div class="panel-heading"><div><h2>Cadastrar produto</h2><p>Adicione um item ao seu catálogo.</p></div></div><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="product.create"><label>Nome<input name="name" maxlength="120" required placeholder="Ex.: Caderno pontilhado"></label><label>Preço (R$)<input name="price" type="number" min="0" step="0.01" required placeholder="0,00"></label><label>Estoque inicial<input name="stock" type="number" min="0" step="1" required placeholder="0"></label><button class="button primary" type="submit">Salvar produto</button></form></section>
            <section class="panel table-panel"><div class="panel-heading"><div><h2>Todos os produtos</h2><p><?= count($products) ?> itens no catálogo</p></div></div><?php if ($products): ?><div class="table-wrap"><table><thead><tr><th>PRODUTO</th><th>PREÇO</th><th>ESTOQUE</th><th></th></tr></thead><tbody><?php foreach ($products as $product): ?><tr><td><span class="product-initial"><?= e(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span><strong class="table-primary"><?= e($product['name']) ?></strong></td><td><?= money($product['price']) ?></td><td><?= e($product['stock']) ?> unidades</td><td class="table-actions"><details class="edit-control"><summary>Editar</summary><form method="post" class="edit-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="product.update"><input type="hidden" name="id" value="<?= e($product['id']) ?>"><label>Nome<input name="name" maxlength="120" required value="<?= e($product['name']) ?>"></label><label>Preço<input name="price" type="number" min="0" step="0.01" required value="<?= e($product['price']) ?>"></label><label>Estoque<input name="stock" type="number" min="0" step="1" required value="<?= e($product['stock']) ?>"></label><button class="button primary" type="submit">Salvar alterações</button></form></details><form method="post" onsubmit="return confirm('Remover este produto? Produtos com vendas não podem ser removidos.');"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="product.delete"><input type="hidden" name="id" value="<?= e($product['id']) ?>"><button class="icon-button" aria-label="Remover <?= e($product['name']) ?>">×</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">Cadastre seu primeiro produto acima.</p><?php endif; ?></section>
        <?php elseif ($page === 'customers'): ?>
            <div class="page-heading"><div><p class="eyebrow">RELACIONAMENTO</p><h1>Clientes</h1><p class="subheading">Mantenha os dados de quem compra com você.</p></div><a class="button primary" href="#new-customer">＋ Novo cliente</a></div>
            <section class="panel form-panel" id="new-customer"><div class="panel-heading"><div><h2>Cadastrar cliente</h2><p>O e-mail é opcional e deve ser único.</p></div></div><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="customer.create"><label>Nome<input name="name" maxlength="120" required placeholder="Nome completo"></label><label>E-mail<input name="email" type="email" maxlength="254" placeholder="nome@exemplo.com"></label><button class="button primary" type="submit">Salvar cliente</button></form></section>
            <section class="panel table-panel"><div class="panel-heading"><div><h2>Todos os clientes</h2><p><?= count($customers) ?> pessoas cadastradas</p></div></div><?php if ($customers): ?><div class="table-wrap"><table><thead><tr><th>CLIENTE</th><th>E-MAIL</th><th>CADASTRO</th><th></th></tr></thead><tbody><?php foreach ($customers as $customer): ?><tr><td><span class="product-initial customer-initial"><?= e(mb_strtoupper(mb_substr($customer['name'], 0, 1))) ?></span><strong class="table-primary"><?= e($customer['name']) ?></strong></td><td><?= e($customer['email'] ?? '—') ?></td><td><?= e(date('d/m/Y', strtotime($customer['created_at']))) ?></td><td class="table-actions"><details class="edit-control"><summary>Editar</summary><form method="post" class="edit-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="customer.update"><input type="hidden" name="id" value="<?= e($customer['id']) ?>"><label>Nome<input name="name" maxlength="120" required value="<?= e($customer['name']) ?>"></label><label>E-mail<input name="email" type="email" maxlength="254" value="<?= e($customer['email'] ?? '') ?>"></label><button class="button primary" type="submit">Salvar alterações</button></form></details><form method="post" onsubmit="return confirm('Remover este cliente?');"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="customer.delete"><input type="hidden" name="id" value="<?= e($customer['id']) ?>"><button class="icon-button" aria-label="Remover <?= e($customer['name']) ?>">×</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">Cadastre seu primeiro cliente acima.</p><?php endif; ?></section>
        <?php else: ?>
            <div class="page-heading"><div><p class="eyebrow">TRANSAÇÕES</p><h1>Vendas</h1><p class="subheading">Registre compras e consulte o histórico de transações.</p></div></div>
            <section class="panel form-panel" id="new-sale"><div class="panel-heading"><div><h2>Registrar venda</h2><p>O preço é preenchido pelo catálogo e o estoque atualizado automaticamente.</p></div></div><?php if ($products): ?><form method="post" class="inline-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="sale.create"><label>Produto<select name="product_id" required><option value="">Selecione um produto</option><?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['name']) ?> · <?= money($product['price']) ?> (<?= e($product['stock']) ?> em estoque)</option><?php endforeach; ?></select></label><label>Cliente<select name="customer_id"><option value="">Cliente avulso</option><?php foreach ($customers as $customer): ?><option value="<?= e($customer['id']) ?>"><?= e($customer['name']) ?></option><?php endforeach; ?></select></label><label>Quantidade<input name="quantity" type="number" min="1" step="1" value="1" required></label><button class="button primary" type="submit">Confirmar venda</button></form><?php else: ?><p class="empty">Cadastre produtos com estoque para registrar uma venda.</p><?php endif; ?></section>
            <section class="panel table-panel">
                <div class="panel-heading"><div><h2>Histórico de vendas</h2><p><?= e($salesPage['total']) ?> transações · página <?= e($salesPage['page']) ?> de <?= e($salesPage['pages']) ?></p></div>
                    <form method="get" class="filter-form"><input type="hidden" name="page" value="sales"><input name="q" value="<?= e($filter) ?>" placeholder="Buscar cliente ou produto" aria-label="Buscar cliente ou produto"><label>De <input name="from" type="date" value="<?= e($fromDate) ?>"></label><label>Até <input name="to" type="date" value="<?= e($toDate) ?>"></label><button class="button secondary" type="submit">Filtrar</button></form>
                </div>
                <?php if ($records): ?><div class="table-wrap"><table><thead><tr><th>PRODUTO</th><th>CLIENTE</th><th>DATA</th><th>QUANTIDADE</th><th>TOTAL</th></tr></thead><tbody><?php foreach ($records as $sale): ?><tr><td><strong class="table-primary"><?= e($sale['product_name']) ?></strong></td><td><?= e($sale['customer_name'] ?? 'Cliente avulso') ?></td><td><?= e(date('d/m/Y H:i', strtotime($sale['sold_at']))) ?></td><td><?= e($sale['quantity']) ?></td><td><strong><?= money((float) $sale['unit_price'] * (int) $sale['quantity']) ?></strong></td></tr><?php endforeach; ?></tbody></table></div>
                    <?php $queryString = ['page' => 'sales', 'q' => $filter, 'from' => $fromDate, 'to' => $toDate]; ?>
                    <?php if ($salesPage['pages'] > 1): ?><nav class="pagination" aria-label="Paginação"><span>Mostrando <?= e(($salesPage['page'] - 1) * 10 + 1) ?>–<?= e(min($salesPage['page'] * 10, $salesPage['total'])) ?> de <?= e($salesPage['total']) ?></span><div><?php if ($salesPage['page'] > 1): $queryString['p'] = $salesPage['page'] - 1; ?><a href="/?<?= e(http_build_query($queryString)) ?>">← Anterior</a><?php endif; ?><?php if ($salesPage['page'] < $salesPage['pages']): $queryString['p'] = $salesPage['page'] + 1; ?><a href="/?<?= e(http_build_query($queryString)) ?>">Próxima →</a><?php endif; ?></div></nav><?php endif; ?>
                <?php else: ?><p class="empty">Nenhuma venda encontrada para esses filtros.</p><?php endif; ?>
            </section>
        <?php endif; ?>
        <footer class="footer">InsightTrack <span>·</span> Indicadores claros para decisões melhores</footer>
    </div>
</main>
</body>
</html>
