<?php

return [
    'user-management' => ['label' => 'User Management', 'permissions' => ['user', 'role', 'permission', 'person', 'customer', 'category-person', 'office-access'], 'controllers' => ['CompanyUser', 'User', 'CompanyRole', 'Role', 'CompanyPermission', 'Permission', 'Customer', 'CategoryPerson', 'OfficeAccess', 'CompanyOfficeAccess']],
    'casting' => ['label' => 'Casting', 'permissions' => ['vacuum-buch', 'vacuum-process', 'vacuum-voucher', 'casting-heating', 'casting-metal-issue', 'casting-release', 'casting-receive', 'tree-cutting-office', 'tree-cutting-issue', 'tree-cutting-receive', 'casting-sorting', 'voucher-history'], 'controllers' => ['VacuumBuch', 'VacuumProcess', 'VacuumVoucher', 'CastingHeating', 'CastingMetalIssue', 'CastingRelease', 'CastingReceive', 'TreeCuttingOffice', 'TreeCuttingIssue', 'TreeCuttingReceive', 'CastingSorting', 'VoucherHistory']],
    'jobwork' => ['label' => 'Jobwork', 'permissions' => ['jobwork-issue', 'jobwork-receive', 'job-worker', 'labour-formula', 'production-cost', 'production-step'], 'controllers' => ['JobworkIssue', 'JobworkReceive', 'JobWorker', 'LabourFormula', 'ProductionCost', 'ProductionStep']],
    'items' => ['label' => 'Item Management', 'permissions' => ['item', 'item-set', 'label-config', 'label-print', 'other-charge'], 'controllers' => ['Item', 'ItemSet', 'LabelConfig', 'ItemLabelPrint', 'LabelPrint', 'OtherCharge']],
    'sales' => ['label' => 'Sales', 'permissions' => ['sale', 'sale-advance', 'approval', 'approval-return'], 'controllers' => ['Sale', 'SaleReturn', 'Approval', 'CustomerAdvance']],
    'reports' => ['label' => 'Reports', 'permissions' => ['report-sales-summary', 'report-purchase-receiver-summary', 'report-stock-position', 'report-approval-summary', 'report-approval-outstanding', 'report-outstanding-amount', 'report-barcode-history', 'report-worker-loss', 'report-approval-return-register'], 'controllers' => ['Report', 'BranchReport']],
    'bullion' => ['label' => 'Bullion Management', 'permissions' => ['bullion'], 'controllers' => ['Bullion']],
    'qr-making' => ['label' => 'QR Making', 'permissions' => ['qr-making'], 'controllers' => ['QrMaking']],
    'stock-gallery' => ['label' => 'Stock Gallery', 'permissions' => ['stock-gallery'], 'controllers' => ['StockGallery', 'StockGalleryMedia', 'ProductBackgroundRemove']],
    'visiting-cards' => ['label' => 'Visiting Cards', 'permissions' => ['report-visiting-cards', 'visiting-card'], 'controllers' => ['VisitingCard']],
    'production-monitor' => ['label' => 'Production Monitor', 'permissions' => ['vacuum-live-dashboard'], 'controllers' => ['VacuumLiveDashboard']],
    'app-theme' => ['label' => 'App Theme', 'permissions' => ['app-theme'], 'controllers' => ['AppTheme']],
    'gst-search' => ['label' => 'GST Search', 'permissions' => ['gst-search'], 'controllers' => ['Gstin', 'GstinLookup', 'GstSearch']],
    'orders' => ['label' => 'Customer Orders', 'permissions' => ['customer-order'], 'controllers' => ['CustomerOrder']],
    'cad' => ['label' => 'CAD Design', 'permissions' => ['cad-design'], 'controllers' => ['CadDesign']],
    'rpt' => ['label' => 'RPT Production', 'permissions' => ['rpt-production'], 'controllers' => ['RptProduction']],
];
