# Technical Specification for the Mobile App

## 1. Goal

Develop a mobile app for store accounting that allows the user to quickly:

- see the current store status;
- create a sale;
- manage products and stock;
- control debts;
- add expenses;
- view basic analytics.

The app must be simple, fast, and optimized for the daily workflow of store sellers and owners.

## 2. Main Roles

### 2.1. Seller

Must have access to:

- dashboard;
- products;
- sales;
- debts;
- personal profile.

Restrictions:

- no store management;
- no user management;
- no administrative settings.

### 2.2. Owner

Must have access to everything available to the seller, plus:

- expenses;
- purchases;
- store users;
- reports;
- store settings.

### 2.3. Super Admin

For `super_admin`, the main working interface should remain in the web/admin panel.
The mobile app may provide only limited access or no dedicated store workflow.

## 3. Bottom Navigation

Recommended bottom navigation items:

- `Home`
- `Products`
- `Sales`
- `Debts`
- `More`

The `More` section should contain:

- expenses;
- purchases;
- reports;
- settings;
- users;
- profile;
- logout.

## 4. Dashboard

The dashboard must be the most useful screen in the app.

### 4.1. Screen Sections

#### Top section

- store name;
- greeting;
- date;
- current shift if needed.

#### Main KPI cards

- today sales;
- today expenses;
- profit;
- total debts.

#### Quick actions

- `New Sale`
- `Add Product`
- `Add Expense`
- `Add Debt`

#### Alerts

- low stock products;
- unpaid debts;
- low activity alerts if needed.

#### Recent activity

- recent sales;
- recent expenses;
- recent debt operations.

### 4.2. Backend data

For mobile convenience, an aggregated endpoint is recommended:

- `GET /api/v1/dashboard`

Suggested response structure:

- `today_sales_total`
- `today_expenses_total`
- `today_profit`
- `debts_total`
- `low_stock_count`
- `recent_sales`
- `recent_expenses`
- `low_stock_products`

## 5. Products Section

This is one of the key sections of the app.

### 5.1. Product list screen

Must include:

- search;
- filters:
  - all;
  - low stock;
  - out of stock;
- product cards list.

Each product card should show:

- product image;
- name;
- code;
- current stock;
- unit;
- sale price;
- stock indicator.

### 5.2. Product details screen

Must include:

- product image;
- name;
- code;
- cost price;
- sale price;
- current stock;
- minimum stock threshold;
- actions:
  - `Edit`;
  - `Add Purchase`;
  - `View Movement`.

### 5.3. Create / edit product screen

Fields:

- image;
- name;
- code;
- unit;
- cost price;
- sale price;
- starting stock;
- minimum stock threshold.

### 5.4. Backend

The current backend already provides:

- product list;
- product details;
- product creation;
- product update;
- product deletion;
- product image upload support.

Additionally, it is recommended to return:

- `is_low_stock`;
- `image_url`.

## 6. Product Movement

This should be a dedicated screen.

### 6.1. Purpose

The user must clearly see how the product came in and how it was sold out.

### 6.2. Screen structure

- product header;
- current stock;
- movement list ordered by date.

### 6.3. Each movement record should include

- date;
- operation type:
  - purchase;
  - sale;
  - correction can be added later;
- quantity;
- price;
- total;
- actor name.

### 6.4. Backend

A dedicated endpoint is needed:

- `GET /api/v1/products/{product}/movements`

Suggested response:

- `current_stock`
- `movements[]`
  - `type`
  - `quantity`
  - `price`
  - `total`
  - `created_at`
  - `reference_id`
  - `reference_type`
  - `actor_name`

Movement sources:

- `purchase_items`
- `sale_items`

## 7. Sales Section

Sales must be created as quickly as possible.

### 7.1. Sales list screen

Must include:

- customer search;
- date filter;
- sales list.

Each record should show:

- ID or number;
- customer name;
- total amount;
- paid amount;
- debt amount;
- date.

### 7.2. Create sale screen

Must include:

- product search;
- add products to cart;
- quantity;
- price;
- discount;
- paid amount;
- payment type:
  - `cash`
  - `card`
  - `transfer`
- final total;
- automatically calculated debt when payment is incomplete.

### 7.3. Sale details screen

Must include:

- customer;
- products list;
- quantities;
- prices;
- discount;
- paid amount;
- debt amount;
- date;
- sale author.

## 8. Debts Section

This is one of the central sections of the app.

### 8.1. Debt list screen

Must include:

- search by person name;
- filters:
  - all;
  - active;
  - closed;
- debt records list.

Each record should show:

- person name;
- balance;
- last operation;
- date.

### 8.2. Debt details screen

Must include:

- person name;
- current balance;
- transaction history;
- actions:
  - `Add Debt`
  - `Repayment`
  - `Correction`

### 8.3. Status logic

The app should display:

- `active debt`;
- `closed`.

### 8.4. Transaction logic

- `give` increases debt;
- `repay` decreases debt;
- `take` decreases debt through return or reverse operation.

## 9. Expenses Section

This section is especially important for the `owner`.

### 9.1. Expenses list screen

Must include:

- search;
- date filter;
- expenses list.

Each record should show:

- name;
- quantity;
- price;
- total;
- date.

### 9.2. Create expense screen

Fields:

- name;
- quantity;
- price;
- note.

### 9.3. Future recommendation

Later, expense categories should be added:

- rent;
- delivery;
- utilities;
- salary;
- other.

## 10. Purchases Section

This is the owner-side inbound stock section.

### 10.1. Purchases list screen

Must include:

- supplier;
- total amount;
- date;
- number of positions.

### 10.2. Create purchase screen

Fields:

- supplier;
- products list;
- quantity;
- price;
- total.

### 10.3. Important logic

After a purchase is created, product stock must be updated automatically.

## 11. Reports Section

On mobile, reports should stay lightweight and easy to understand.

### 11.1. Minimum report set

- sales;
- expenses;
- profit;
- stock;
- low stock;
- debts.

### 11.2. Filters

- today;
- 7 days;
- 30 days;
- custom period.

### 11.3. Format

Recommended format:

- KPI cards;
- one simple chart;
- one list of key problem points.

Complex BI-style analytics should not overload the mobile UI.

## 12. More Section

Must include:

- profile;
- settings;
- users;
- currency;
- store;
- logout.

For `seller`, the list must be shorter and should exclude owner/admin features.

## 13. UI/UX Requirements

### 13.1. General principles

The interface must be:

- fast;
- clear;
- minimal;
- focused on frequent daily actions.

### 13.2. Rules

- minimal nesting;
- minimal heavy tables;
- more cards and large CTA buttons;
- important numbers must be visible immediately;
- the dashboard should contain only essential information;
- product images should be used in product lists and product details;
- sale creation must require as few steps as possible.

## 14. What already exists in the backend

The current backend already provides a solid base for:

- authentication;
- shops;
- users;
- products;
- sales;
- purchases;
- expenses;
- debts;
- reports;
- product image upload.

## 15. Recommended backend additions

For a better mobile UX, the following endpoints are recommended:

- `GET /api/v1/dashboard`
- `GET /api/v1/products/{product}/movements`
- `GET /api/v1/products/low-stock` or an equivalent filter
- `GET /api/v1/sales/recent` or include it in dashboard
- `GET /api/v1/debts/summary` or include it in dashboard

## 16. Development Priority

### Phase 1

- login;
- dashboard;
- products;
- product details;
- sales;
- debts.

### Phase 2

- expenses;
- purchases;
- product movement;
- simple report.

### Phase 3

- users;
- settings;
- advanced reports.

## 17. Final MVP Scope

For the first complete version, the recommended screen set is:

- login;
- dashboard;
- products;
- product details;
- product movement;
- sales;
- debts;
- expenses;
- `More` section.

This scope covers the main daily store workflow and matches the client expectations from the provided sketches and references.
