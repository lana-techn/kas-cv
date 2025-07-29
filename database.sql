-- Creating database for Sistem Informasi Pengelolaan Kas
CREATE DATABASE IF NOT EXISTS cv_kwas;
USE cv_kwas;

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
CREATE TABLE `supplier` (
  `id_supplier` varchar(25) NOT NULL,
  `nama_supplier` varchar(200) NOT NULL,
  `alamat` varchar(200) NOT NULL,
  `no_telpon` varchar(15) NOT NULL,
  PRIMARY KEY (`id_supplier`)
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
    tgl_produksi DATE NOT NULL,
    status ENUM('Proses', 'Selesai') NOT NULL DEFAULT 'Proses',
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


-- Insert default user dengan password_hash
INSERT INTO user (id_user, username, password, level) VALUES
('ADM001', 'admin', '$2y$10$Yxl2I/QQ157yxazUkNjdQeUXjgNjzda898P8ybxamWIZe0/PR6PtK', 'admin'),
('PGW001', 'pegawai', '$2y$10$T3l3s99dCR.f0PpzVWSOxu8NTM4lHk8pvWIXaUR52He3uuFtuYI92', 'pegawai'), 
('PMK001', 'pemilik', '$2y$10$gn83LAxQj61peFixbUH2rOvcYkFO8wxIcLLULDu6GCw11/iCvpJuq', 'pemilik');

-- Insert dummy suppliers
INSERT INTO supplier (id_supplier, nama_supplier, alamat, no_telpon) VALUES
('SUP001', 'PT Bahan Baku Utama', 'Jl. Industri No. 123, Jakarta', '021-5551234'),
('SUP002', 'CV Material Sejahtera', 'Jl. Merdeka No. 45, Bandung', '022-4445678'),
('SUP003', 'UD Makmur Jaya', 'Jl. Pahlawan No. 67, Surabaya', '031-3334567');

-- Insert dummy materials (bahan)
INSERT INTO bahan (kd_bahan, nama_bahan, stok, satuan) VALUES
('BHN001', 'Kayu Jati', 100, 'Meter'),
('BHN002', 'Cat Kayu', 50, 'Liter'),
('BHN003', 'Paku', 1000, 'Kg'),
('BHN004', 'Lem Kayu', 75, 'Liter'),
('BHN005', 'Amplas', 200, 'Lembar');

-- Insert dummy products (barang)
INSERT INTO barang (kd_barang, nama_barang, stok) VALUES
('BRG001', 'Meja Makan', 10),
('BRG002', 'Kursi Tamu', 24),
('BRG003', 'Lemari Pakaian', 5),
('BRG004', 'Rak Buku', 15);

-- Insert dummy production records
INSERT INTO produksi (id_produksi, kd_barang, tgl_produksi, status, jumlah_produksi) VALUES
('PRD001', 'BRG001', '2025-07-01', 'Selesai', 5),
('PRD002', 'BRG002', '2025-07-05', 'Selesai', 12),
('PRD003', 'BRG003', '2025-07-10', 'Proses', 3);

-- Insert dummy production details
INSERT INTO detail_produksi (id_detproduksi, id_produksi, kd_bahan, satuan, jum_bahan) VALUES
('DPR001', 'PRD001', 'BHN001', 'Meter', 10),
('DPR002', 'PRD001', 'BHN002', 'Liter', 5),
('DPR003', 'PRD002', 'BHN001', 'Meter', 24),
('DPR004', 'PRD003', 'BHN001', 'Meter', 9);



