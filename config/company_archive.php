<?php

return [
    'enabled' => env('COMPANY_ARCHIVE_ENABLED', false),
    'disk' => env('COMPANY_ARCHIVE_DISK', 'local'),
    'max_bytes' => 2 * 1024 * 1024 * 1024,
    'max_upload_kb' => 2 * 1024 * 1024,
    // Every table needs an explicit policy. Unknown tables block destructive actions.
    'business' => explode(' ', 'approval_carts approval_headers approval_items audit_logs cad_designs casting_heating_items casting_metal_issue_items casting_release_items casting_sorting_items category_people company_activity_notifications customer_advance_ledgers customer_advance_voucher_items customer_advance_vouchers customer_order_lines customer_order_vouchers customer_orders customers item_labels item_sets items job_workers jobwork_issue_items jobwork_issues jobwork_raw_stock_allocations jobwork_receive_items jobwork_receives label_configs labour_formulas marketing_qrs metal_ledgers order_production_events order_production_files other_charges production_costs production_step_user production_steps return_carts rpt_batches sale_carts sale_items sale_payments sale_return_items sale_returns sales stock_gallery_media tree_cutting_issue_items tree_cutting_office_items tree_cutting_receive_items vacuum_buch_weight_histories vacuum_buchs vacuum_processes vacuum_voucher_items vacuum_vouchers visiting_cards worker_access_logs product_background_removes'),
    'retained' => explode(' ', 'companies users roles permissions model_has_roles model_has_permissions role_has_permissions company_app_themes company_office_access_settings company_plan_renewals company_voucher_counters worker_allowed_devices'),
    'system' => explode(' ', 'migrations cache cache_locks jobs job_batches failed_jobs sessions password_reset_tokens password_set_tokens personal_access_tokens super_admin_notifications super_admins company_archive_states'),
    // The legacy controller exposes these records globally when no ownership column exists.
    // They remain shared on the server; never guess that they belong to the selected company.
    'shared_without_owner' => ['product_background_removes'],
    // Logical relationships also cover legacy tables without declared foreign keys.
    'parents' => [
        'approval_items' => ['approval_id', 'approval_headers'],
        'customer_advance_voucher_items' => ['voucher_id', 'customer_advance_vouchers'],
        'customer_order_lines' => ['customer_order_id', 'customer_orders'],
        'cad_designs' => ['customer_order_line_id', 'customer_order_lines'],
        'rpt_batches' => ['cad_design_id', 'cad_designs'],
        'order_production_files' => ['customer_order_id', 'customer_orders'],
        'order_production_events' => ['customer_order_id', 'customer_orders'],
        'customer_order_vouchers' => ['customer_order_id', 'customer_orders'],
        'jobwork_issue_items' => ['jobwork_issue_id', 'jobwork_issues'],
        'jobwork_receive_items' => ['jobwork_receive_id', 'jobwork_receives'],
        'vacuum_voucher_items' => ['vacuum_voucher_id', 'vacuum_vouchers'],
        'sale_items' => ['sale_id', 'sales'], 'sale_return_items' => ['sale_return_id', 'sale_returns'],
        'production_step_user' => ['production_step_id', 'production_steps'],
        'product_background_removes' => ['created_by', 'users'],
        'item_labels' => ['item_id', 'items'], 'metal_ledgers' => ['sale_id', 'sales'],
        'vacuum_buch_weight_histories' => ['vacuum_buch_id', 'vacuum_buchs'],
    ],
    'references' => [
        'customer_id'=>'customers', 'item_id'=>'items', 'product_id'=>'items', 'itemset_id'=>'item_sets', 'item_set_id'=>'item_sets',
        'sale_id'=>'sales', 'sale_item_id'=>'sale_items', 'sale_return_id'=>'sale_returns',
        'approval_id'=>'approval_headers', 'approval_item_id'=>'approval_items',
        'jobwork_issue_id'=>'jobwork_issues', 'jobwork_issue_item_id'=>'jobwork_issue_items', 'jobwork_receive_id'=>'jobwork_receives',
        'vacuum_voucher_id'=>'vacuum_vouchers', 'vacuum_voucher_item_id'=>'vacuum_voucher_items', 'vacuum_buch_id'=>'vacuum_buchs',
        'casting_release_item_id'=>'casting_release_items', 'tree_cutting_issue_item_id'=>'tree_cutting_issue_items',
        'customer_order_id'=>'customer_orders', 'customer_order_line_id'=>'customer_order_lines', 'cad_design_id'=>'cad_designs', 'rpt_batch_id'=>'rpt_batches',
        'production_step_id'=>'production_steps', 'production_cost_id'=>'production_costs', 'labour_formula_id'=>'labour_formulas',
        'label_config_id'=>'label_configs', 'ledger_id'=>'customer_advance_ledgers', 'job_worker_id'=>'job_workers',
    ],
    // File rules are reviewed independently of database ownership.
    'files' => [
        'companies' => [['company_logo', 'profile-asset']],
        'users' => [['profile_image', 'profile-asset']],
        'item_sets' => [['image_path', '@image_disk']],
        'stock_gallery_media' => [['original_path', 'local'], ['cutout_path', 'local'], ['source_path', '@source_disk']],
        'order_production_files' => [['path', 'local']],
        'visiting_cards' => [['image_path', 'public']],
        'marketing_qrs' => [['logo_path', 'local']],
        'product_background_removes' => [['original_image', 'public-root'], ['removed_image', 'public-root'], ['thumbnail_image', 'public-root']],
    ],
];
