# Admin Dashboard Navigation Flowchart

## How to Access Inventory, Budget, and Certificate Sections

```mermaid
graph TD
    A[Login to Application] --> B{Are you logged in as Admin?}
    B -- Yes --> C[Access Admin Dashboard]
    B -- No --> D[Login as Admin User]
    D --> C
    
    C --> E[Admin Dashboard Page]
    E --> F[Scroll Down to "Financial & Inventory Management" Section]
    
    F --> G[Look for 4 Card Sections in a Row:]
    G --> H[Budget Management Card]
    G --> J[Inventory Management Card]
    G --> K[Certificate Management Card]
    G --> I[Fee Management Card]
    
    H --> L[Click "Budget Settings" → admin.budgets.index]
    H --> M[Click "Expense Tracking" → admin.expenses.index]
    
    I --> N[Click "Assets Management" → admin.assets.index]
    I --> O[Click "Inventory Dashboard" → admin.inventory.index]
    
    J --> P[Click "Assets" → admin.assets.index]
    J --> Q[Click "Equipment" → admin.inventory.index]
    
    K --> R[Click "Certificates" → admin.certificates.index]
    K --> S[Click "Templates" → admin.certificate-templates.index]
    
    style H fill:#ffe4b5,stroke:#333,stroke-width:2px
    style J fill:#ffe4b5,stroke:#333,stroke-width:2px
    style K fill:#ffe4b5,stroke:#333,stroke-width:2px
```

## Detailed Steps:

1. **Login**: Access the application and login with admin credentials
2. **Navigate**: Go to the Admin Dashboard (usually `/admin` or `/admin/dashboard`)
3. **Scroll Down**: Look for the section titled "Financial & Inventory Management" - this is located after Core Management Systems, Academic & Examination Management, and Class & Schedule Management sections. You need to scroll down quite a bit.
4. **Find the Cards**: You should see 4 cards in a horizontal row:
   - 🟨 **Budget Management** (Yellow/Warning color)
   - 🟦 **Library Management** (Blue/Info color) 
   - 🟥 **Inventory Management** (Red/Danger color)
   - 🟩 **Certificate Management** (Green/Success color)

## If You Still Can't See Them:

1. **Scroll Further Down**: The sections are located in the "Financial & Inventory Management" section which comes after Core Management Systems, Academic & Examination Management, and Class & Schedule Management sections.
2. **Clear Browser Cache**: Press Ctrl+F5 or Ctrl+Shift+R
3. **Check User Role**: Ensure you're logged in as an admin user
4. **Verify URL**: Make sure you're on the correct admin dashboard page
5. **Check File**: The sections should be in `resources/views/admin-dashboard.blade.php` lines 282-359

## Troubleshooting:

- **Browser Issue**: Try a different browser or incognito mode
- **Permissions**: Contact system administrator to verify admin access
- **File Missing**: Check if `admin-dashboard.blade.php` has been modified
- **Route Issues**: Run `php artisan route:list` to verify routes exist

The sections are definitely in the file and should be visible on the admin dashboard page.