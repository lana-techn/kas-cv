CREATE DATABASE karya_wahana_sentosa;
USE karya_wahana_sentosa;

CREATE TABLE users (
    id_user VARCHAR(10) PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    level ENUM('admin', 'pegawai', 'pemilik') NOT NULL
);

CREATE TABLE materials (
    kd_bahan VARCHAR(10) PRIMARY KEY,
    nama_bahan VARCHAR(100) NOT NULL,
    stok INT NOT NULL,
    satuan VARCHAR(20) NOT NULL
);

CREATE TABLE products (
    kd_barang VARCHAR(10) PRIMARY KEY,
    nama_barang VARCHAR(100) NOT NULL,
    stok INT NOT NULL
);

CREATE TABLE suppliers (
    id_supplier VARCHAR(10) PRIMARY KEY,
    nama_supplier VARCHAR(100) NOT NULL,
    alamat TEXT NOT NULL,
    no_telpon VARCHAR(20) NOT NULL
);

CREATE TABLE productions (
    id_produksi VARCHAR(10) PRIMARY KEY,
    kd_barang VARCHAR(10) NOT NULL,
    nama_barang VARCHAR(100) NOT NULL,
    jumlah_produksi INT NOT NULL,
    FOREIGN KEY (kd_barang) REFERENCES products(kd_barang)
);

CREATE TABLE costs (
    id_biaya VARCHAR(10) PRIMARY KEY,
    nama_biaya VARCHAR(100) NOT NULL,
    tgl_biaya DATE NOT NULL,
    total INT NOT NULL
);

CREATE TABLE purchases (
    id_pembelian VARCHAR(10) PRIMARY KEY,
    tgl_beli DATE NOT NULL,
    id_supplier VARCHAR(10) NOT NULL,
    total_beli INT NOT NULL,
    bayar INT NOT NULL,
    kembali INT NOT NULL,
    FOREIGN KEY (id_supplier) REFERENCES suppliers(id_supplier)
);

CREATE TABLE purchase_items (
    id_pembelian VARCHAR(10) NOT NULL,
    kd_bahan VARCHAR(10) NOT NULL,
    harga_beli INT NOT NULL,
    qty INT NOT NULL,
    subtotal INT NOT NULL,
    FOREIGN KEY (id_pembelian) REFERENCES purchases(id_pembelian),
    FOREIGN KEY (kd_bahan) REFERENCES materials(kd_bahan)
);

CREATE TABLE sales (
    id_penjualan VARCHAR(10) PRIMARY KEY,
    tgl_jual DATE NOT NULL,
    total_jual INT NOT NULL,
    bayar INT NOT NULL,
    kembali INT NOT NULL
);

CREATE TABLE sale_items (
    id_penjualan VARCHAR(10) NOT NULL,
    kd_barang VARCHAR(10) NOT NULL,
    harga_jual INT NOT NULL,
    qty INT NOT NULL,
    subtotal INT NOT NULL,
    FOREIGN KEY (id_penjualan) REFERENCES sales(id_penjualan),
    FOREIGN KEY (kd_barang) REFERENCES products(kd_barang)
);

-- Insert default users
INSERT INTO users (id_user, username, password, level) VALUES
('ADM001', 'admin', MD5('admin'), 'admin'),
('PGW001', 'pegawai', MD5('pegawai'), 'pegawai'),
('PMK001', 'pemilik', MD5('pemilik'), 'pemilik');