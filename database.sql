-- Creating database for Sistem Informasi Pengelolaan Kas
CREATE DATABASE IF NOT EXISTS karya_wahana_sentosa;
USE karya_wahana_sentosa;

-- Table User
CREATE TABLE user (
    id_user VARCHAR(25) PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(60) NOT NULL,
    level ENUM('admin', 'pegawai', 'pemilik') NOT NULL
);

-- Table Bahan
CREATE TABLE bahan (
    kd_bahan VARCHAR(25) PRIMARY KEY,
    nama_bahan VARCHAR(200) NOT NULL,
    stok INTEGER NOT NULL,
    satuan VARCHAR(15) NOT NULL
);

-- Table Barang
CREATE TABLE barang (
    kd_barang VARCHAR(25) PRIMARY KEY,
    nama_barang VARCHAR(200) NOT NULL,
    stok INTEGER NOT NULL
);

-- Table Supplier
CREATE TABLE supplier (
    id_supplier VARCHAR(25) PRIMARY KEY,
    nama_supplier VARCHAR(200) NOT NULL,
    alamat VARCHAR(200) NOT NULL,
    no_telpon INTEGER NOT NULL
);

-- Table Biaya
CREATE TABLE biaya (
    id_biaya VARCHAR(25) PRIMARY KEY,
    nama_biaya VARCHAR(200) NOT NULL,
    tgl_biaya DATE NOT NULL,
    total INTEGER NOT NULL
);

-- Table Produksi
CREATE TABLE produksi (
    id_produksi VARCHAR(25) PRIMARY KEY,
    kd_barang VARCHAR(25) NOT NULL,
    nama_barang VARCHAR(200) NOT NULL,
    jumlah_produksi INTEGER NOT NULL,
    FOREIGN KEY (kd_barang) REFERENCES barang(kd_barang)
);

-- Table Detail Produksi
CREATE TABLE detail_produksi (
    id_detproduksi VARCHAR(25) PRIMARY KEY,
    id_produksi VARCHAR(25) NOT NULL,
    kd_bahan VARCHAR(25) NOT NULL,
    satuan VARCHAR(25) NOT NULL,
    jum_bahan INTEGER NOT NULL,
    FOREIGN KEY (id_produksi) REFERENCES produksi(id_produksi),
    FOREIGN KEY (kd_bahan) REFERENCES bahan(kd_bahan)
);

-- Table Pembelian
CREATE TABLE pembelian (
    id_pembelian VARCHAR(25) PRIMARY KEY,
    tgl_beli DATE NOT NULL,
    total_beli INTEGER NOT NULL,
    id_supplier VARCHAR(25) NOT NULL,
    bayar INTEGER NOT NULL,
    kembali VARCHAR(25) NOT NULL,
    FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier)
);

-- Table Detail Pembelian
CREATE TABLE detail_pembelian (
    id_detail_beli VARCHAR(25) PRIMARY KEY,
    id_pembelian VARCHAR(25) NOT NULL,
    kd_bahan VARCHAR(25) NOT NULL,
    harga_beli INTEGER NOT NULL,
    qty INTEGER NOT NULL,
    subtotal VARCHAR(25) NOT NULL,
    FOREIGN KEY (id_pembelian) REFERENCES pembelian(id_pembelian),
    FOREIGN KEY (kd_bahan) REFERENCES bahan(kd_bahan)
);

-- Table Penjualan
CREATE TABLE penjualan (
    id_penjualan VARCHAR(25) PRIMARY KEY,
    tgl_jual DATE NOT NULL,
    total_jual INTEGER NOT NULL,
    bayar INTEGER NOT NULL,
    kembali VARCHAR(25) NOT NULL
);

-- Table Detail Penjualan
CREATE TABLE detail_penjualan (
    id_detail_jual VARCHAR(25) PRIMARY KEY,
    id_penjualan VARCHAR(25) NOT NULL,
    kd_barang VARCHAR(25) NOT NULL,
    harga_jual INTEGER NOT NULL,
    qty INTEGER NOT NULL,
    subtotal VARCHAR(25) NOT NULL,
    FOREIGN KEY (id_penjualan) REFERENCES penjualan(id_penjualan),
    FOREIGN KEY (kd_barang) REFERENCES barang(kd_barang)
);

-- Table Penerimaan Kas
CREATE TABLE penerimaan_kas (
    id_penerimaan_kas VARCHAR(25) PRIMARY KEY,
    id_penjualan VARCHAR(25) NOT NULL,
    tgl_terima_kas DATE NOT NULL,
    uraian VARCHAR(200) NOT NULL,
    total VARCHAR(25) NOT NULL,
    FOREIGN KEY (id_penjualan) REFERENCES penjualan(id_penjualan)
);

-- Table Pengeluaran Kas
CREATE TABLE pengeluaran_kas (
    id_pengeluaran_kas VARCHAR(25) PRIMARY KEY,
    id_pembelian VARCHAR(25),
    id_biaya VARCHAR(25),
    tgl_pengeluaran_kas DATE NOT NULL,
    uraian VARCHAR(200) NOT NULL,
    total VARCHAR(25) NOT NULL,
    FOREIGN KEY (id_pembelian) REFERENCES pembelian(id_pembelian),
    FOREIGN KEY (id_biaya) REFERENCES biaya(id_biaya)
);

-- Table Kas
CREATE TABLE kas (
    id_kas VARCHAR(25) PRIMARY KEY,
    id_penerimaan_kas VARCHAR(25),
    id_pengeluaran_kas VARCHAR(25),
    tanggal DATE NOT NULL,
    keterangan VARCHAR(50) NOT NULL,
    debit INTEGER NOT NULL,
    kredit INTEGER NOT NULL,
    saldo INTEGER NOT NULL,
    FOREIGN KEY (id_penerimaan_kas) REFERENCES penerimaan_kas(id_penerimaan_kas),
    FOREIGN KEY (id_pengeluaran_kas) REFERENCES pengeluaran_kas(id_pengeluaran_kas)
);


-- Insert default user
INSERT INTO user (id_user, username, password, level) VALUES
('ADM001', 'admin', MD5('admin'), 'admin'),
('PGW001', 'pegawai', MD5('pegawai'), 'pegawai'),
('PMK001', 'pemilik', MD5('pemilik'), 'pemilik');
