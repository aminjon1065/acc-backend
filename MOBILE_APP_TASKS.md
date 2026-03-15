# Mobile App Tasks

Project: CK Accounting Mobile App  
Backend: Laravel API (`/api/v1`)  
Last updated: 2026-03-15

---

## 1. Core Setup

- [ ] Initialize Expo project structure for production use
- [ ] Configure environments for local / staging / production API base URLs
- [ ] Add secure token storage for mobile auth
- [ ] Configure API client with `Authorization: Bearer` support
- [ ] Add global API error handling
- [ ] Add request timeout and retry strategy for unstable mobile networks

---

## 2. Authentication

- [ ] Implement login screen
- [ ] Implement session restore on app launch
- [ ] Implement logout flow
- [ ] Implement token refresh flow
- [ ] Handle `403 Shop is suspended` state with dedicated screen
- [ ] Handle `429 Too Many Requests` on login with proper UX

---

## 3. Role-Based App Access

### Super Admin

- [ ] Dashboard entry for super admin
- [ ] Shops list screen
- [ ] Create shop screen
- [ ] Update shop screen
- [ ] Suspend / activate shop action
- [ ] Users screen across shops
- [ ] Assign owner to shop flow
- [ ] Currency management screen
- [ ] Reports screen with shop filter
- [ ] Settings access by selected shop

### Owner

- [ ] Owner home dashboard
- [ ] Own shop profile screen
- [ ] Seller management list
- [ ] Create seller flow
- [ ] Update seller flow
- [ ] Delete seller flow
- [ ] Products management
- [ ] Purchases management
- [ ] Sales management
- [ ] Expenses management
- [ ] Debts management
- [ ] Reports screen
- [ ] Shop settings screen

### Seller

- [ ] Seller home screen
- [ ] Product list and stock view
- [ ] Sale creation flow
- [ ] Sales history view
- [ ] Own profile screen
- [ ] Own shop info screen

---

## 4. Module Screens

### Products

- [ ] Product list screen
- [ ] Product details screen
- [ ] Create product screen
- [ ] Update product screen
- [ ] Delete product action
- [ ] Low stock indicator in list

### Sales

- [ ] Sales list screen
- [ ] Sale details screen
- [ ] Create sale screen
- [ ] Product picker for sale items
- [ ] Debt and payment summary UI

### Purchases

- [ ] Purchases list screen
- [ ] Purchase details screen
- [ ] Create purchase screen
- [ ] Product picker for purchase items

### Expenses

- [ ] Expenses list screen
- [ ] Expense details screen
- [ ] Create expense screen
- [ ] Update expense screen
- [ ] Delete expense action

### Debts

- [ ] Debts list screen
- [ ] Debt details screen
- [ ] Create debt screen
- [ ] Add debt transaction flow
- [ ] Balance history view

### Reports

- [ ] Sales report screen
- [ ] Expense report screen
- [ ] Profit report screen
- [ ] Stock report screen
- [ ] Date range filter UI

### Shops and Users

- [ ] Shops list / detail screens for super admin
- [ ] Users list screen
- [ ] User create / edit / delete flows by role rules

### Settings and Currencies

- [ ] Shop settings screen
- [ ] Currency list screen
- [ ] Currency update flow for super admin

---

## 5. UX and State Handling

- [ ] Add splash/loading bootstrap flow
- [ ] Add empty states for all list pages
- [ ] Add offline/error state components
- [ ] Add pull-to-refresh on list screens
- [ ] Add pagination / load more support
- [ ] Add success and error toast system
- [ ] Add role-aware navigation and screen guards

---

## 6. Backend Integration Gaps To Plan For

- [ ] Confirm exact seller-visible screens with backend role matrix
- [ ] Confirm whether seller needs sales history or only create sale
- [ ] Confirm whether owner needs full sales / purchases / debts edit lifecycle
- [ ] Confirm whether super admin needs global analytics screens beyond current reports
- [ ] Plan support for future device/session management endpoints
- [ ] Plan support for audit log visibility if required in admin app

---

## 7. Technical Improvements For Mobile

- [ ] Centralize API types from backend contract
- [ ] Add form validation aligned with backend validation errors
- [ ] Add query caching strategy for lists and reports
- [ ] Add analytics / crash reporting
- [ ] Add app version check / force update mechanism
- [ ] Add push notification token registration when backend endpoint is ready
- [ ] Add localization strategy for Russian / Tajik if required

---

## 8. QA Checklist

- [ ] Verify login/logout/refresh on iOS simulator
- [ ] Verify login/logout/refresh on Android emulator
- [ ] Verify suspended shop handling
- [ ] Verify role-based access for super admin
- [ ] Verify role-based access for owner
- [ ] Verify role-based access for seller
- [ ] Verify API error handling for `401`, `403`, `404`, `422`, `429`
- [ ] Verify pagination behavior on large lists
- [ ] Verify sales flow with insufficient stock error
- [ ] Verify debt transaction flow and balances

---

## 9. Recommended Delivery Order

1. Auth + token handling + role-aware navigation
2. Seller flow: products view + create sale
3. Owner flow: sellers + products + sales + expenses
4. Purchases + debts + reports
5. Super admin flow: shops + owners + currencies + cross-shop reports
6. Polish: offline states, retries, analytics, push, versioning
