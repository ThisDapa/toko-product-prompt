-- Tabel products
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_produk VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    fee DECIMAL(10, 2) DEFAULT 0,
    thumbnail VARCHAR(255),
    prompt_file VARCHAR(255),
    badge VARCHAR(50),
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel product_photos
CREATE TABLE product_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tabel transaksi
CREATE TABLE transaksi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaksi_id VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    kode_produk INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kode_produk) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Tabel users
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);