CREATE TABLE customers (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(254) UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE products (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    price NUMERIC(10, 2) NOT NULL CHECK (price >= 0),
    stock INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Uma venda corresponde a um produto para manter o MVP simples.
CREATE TABLE sales (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    customer_id BIGINT REFERENCES customers(id) ON DELETE SET NULL,
    product_id BIGINT NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(10, 2) NOT NULL CHECK (unit_price >= 0),
    sold_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX sales_sold_at_idx ON sales (sold_at DESC);
CREATE INDEX sales_customer_id_idx ON sales (customer_id);

-- Estes registros tornam o painel demonstrável em um banco recém-criado.
INSERT INTO customers (name, email) VALUES
    ('Mariana Costa', 'mariana@example.com'),
    ('Rafael Almeida', 'rafael@example.com'),
    ('Camila Oliveira', 'camila@example.com'),
    ('Lucas Ferreira', 'lucas@example.com');

INSERT INTO products (name, price, stock) VALUES
    ('Caderno pontilhado', 42.90, 38),
    ('Caneta tinteiro', 89.00, 21),
    ('Planner semanal', 64.50, 17),
    ('Kit marcadores', 36.00, 4),
    ('Estojo de lona', 55.00, 26);

INSERT INTO sales (customer_id, product_id, quantity, unit_price, sold_at) VALUES
    (1, 1, 2, 42.90, NOW() - INTERVAL '165 days'),
    (2, 2, 1, 89.00, NOW() - INTERVAL '152 days'),
    (3, 4, 3, 36.00, NOW() - INTERVAL '138 days'),
    (4, 3, 1, 64.50, NOW() - INTERVAL '121 days'),
    (1, 5, 2, 55.00, NOW() - INTERVAL '109 days'),
    (2, 1, 4, 42.90, NOW() - INTERVAL '94 days'),
    (3, 2, 2, 89.00, NOW() - INTERVAL '82 days'),
    (4, 4, 2, 36.00, NOW() - INTERVAL '68 days'),
    (1, 3, 2, 64.50, NOW() - INTERVAL '51 days'),
    (2, 5, 1, 55.00, NOW() - INTERVAL '39 days'),
    (3, 1, 3, 42.90, NOW() - INTERVAL '23 days'),
    (4, 2, 1, 89.00, NOW() - INTERVAL '8 days');
