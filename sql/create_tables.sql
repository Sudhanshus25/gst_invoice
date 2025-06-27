CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    gstin VARCHAR(20),
    pan VARCHAR(10),
    billing_state VARCHAR(50),
    place_of_supply VARCHAR(100),
    email VARCHAR(100)
);

CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    hsn_sac VARCHAR(10),
    price DECIMAL(10, 2),
    tax_inclusive BOOLEAN,
    tax_type ENUM('GST', 'IGST')
);

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50),
    company_id INT,
    date DATE,
    reverse_charge BOOLEAN,
    cgst DECIMAL(10,2),
    sgst DECIMAL(10,2),
    igst DECIMAL(10,2),
    total DECIMAL(10,2),
    pdf_path VARCHAR(255),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);

CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT,
    item_id INT,
    quantity INT,
    rate DECIMAL(10,2),
    tax_amount DECIMAL(10,2),
    total DECIMAL(10,2),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (item_id) REFERENCES items(id)
);
