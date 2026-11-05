<?php

declare(strict_types=1);

/*
 * Admin page builders. admin_page() is the back-office shell: a light navbar,
 * a sidebar, flash messages, and the content area.
 */

use Lightpull\Response;

function admin_page(string $title, array $parts, string $active = '', int $status = 200): Response
{
    $links = [
        'Dashboard' => '/admin',
        'Products' => '/admin/products',
        'Categories' => '/admin/categories',
        'Inquiries' => '/admin/inquiries',
    ];
    if (session()->isAdmin()) {
        $links['Users'] = '/admin/users';
    }

    return page($title . ' · Admin', [
        navbar([
            'brand' => 'BLF Admin',
            'brand_url' => '/admin',
            'color' => 'light',
            'size' => 'sm',
            'fluid' => true,
            'text' => 'Signed in as ' . (session()->user()['name'] ?? ''),
            'right' => ['View site' => '/'],
            'right_post' => ['Log out' => '/admin/logout'],
        ]),
        container([row([
            col([sidebar(['links' => $links, 'active' => $active])], 2),
            col([flash_messages(), ...$parts], 10),
        ])], ['fluid' => true]),
    ], ['status' => $status]);
}

function admin_login(): Response
{
    return page('Staff sign in', [
        container([
            section_title(['title' => 'Staff sign in', 'subtitle' => 'Betis Legacy Furniture back office', 'size' => 'lg']),
            flash_messages(),
            form_open(['action' => '/admin/login']),
            input(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'required' => true, 'autocomplete' => 'username']),
            input(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'current-password']),
            submit(['label' => 'Sign in', 'cancel' => ['Back to the site', '/']]),
            form_close(),
        ], ['width' => 'narrow']),
    ]);
}

// ---------------------------------------------------------------- dashboard

function inquiry_table(array $inquiries, string $empty): string
{
    return data_table([
        'columns' => [
            'reference_no' => ['Reference', 'strong'],
            'customer_name' => 'Customer',
            'phone' => 'Phone',
            'product' => 'Product',
            'status_badge' => ['Status', 'badge'],
            'created_at' => ['Received', 'date'],
        ],
        'rows' => array_map(fn (array $i) => $i + [
            'product' => $i['product_name'] ?? 'General inquiry',
            'status_badge' => ['text' => status_label($i['status']), 'color' => status_color($i['status'])],
        ], $inquiries),
        'actions' => fn (array $i) => [['View', '/admin/inquiries/' . $i['id']]],
        'empty' => $empty,
    ]);
}

function admin_dashboard(array $d): Response
{
    $tiles = [
        ['label' => 'New inquiries', 'value' => $d['new'], 'color' => 'secondary', 'link' => '/admin/inquiries?status=new'],
        ['label' => 'Awaiting quote', 'value' => $d['awaiting_quote'], 'color' => 'info', 'link' => '/admin/inquiries?status=new,contacted'],
        ['label' => 'In production', 'value' => $d['in_production'], 'color' => 'warning', 'link' => '/admin/inquiries?status=confirmed,in_production'],
        ['label' => 'Active products', 'value' => $d['active_products'], 'color' => 'success', 'link' => '/admin/products'],
    ];
    return admin_page('Dashboard', [
        section_title(['title' => 'Dashboard', 'size' => 'lg']),
        row(array_map(fn (array $tile) => col([stat_card($tile)], 3), $tiles)),
        section_title(['title' => 'Latest inquiries', 'action' => ['View all', '/admin/inquiries']]),
        inquiry_table($d['recent'], 'No inquiries yet.'),
    ], 'Dashboard');
}

// ---------------------------------------------------------------- products

function admin_products(array $d): Response
{
    $pills = ['All' => '/admin/products'];
    $active = 'All';
    foreach ($d['categories'] as $id => $name) {
        $pills[$name] = '/admin/products?category=' . $id;
        $active = $d['category'] === (string) $id ? $name : $active;
    }
    $query = array_filter(['category' => $d['category'], 'q' => $d['q']], fn ($v) => $v !== '');

    return admin_page('Products', [
        section_title(['title' => 'Products', 'subtitle' => $d['total'] . ' total', 'size' => 'lg', 'action' => ['Add product', '/admin/products/create']]),
        pills(['items' => $pills, 'active' => $active]),
        search_form(['action' => '/admin/products', 'value' => $d['q'], 'placeholder' => 'Search products', 'hidden' => ['category' => $d['category']]]),
        data_table([
            'columns' => [
                'thumb' => ['Photo', 'image'],
                'name' => ['Name', 'strong'],
                'category_name' => 'Category',
                'price' => ['Price', 'money'],
                'stock' => ['Stock', 'badge'],
                'active' => ['Visibility', 'badge'],
            ],
            'rows' => array_map(fn (array $p) => $p + [
                'thumb' => ['src' => product_image($p), 'alt' => ''],
                'stock' => stock_badge($p),
                'active' => $p['is_active'] ? ['text' => 'Active', 'color' => 'success'] : ['text' => 'Hidden', 'color' => 'dark'],
            ], $d['products']),
            'actions' => fn (array $p) => [
                ['Edit', '/admin/products/' . $p['id'] . '/edit'],
                ['Delete', '/admin/products/' . $p['id'] . '/delete', 'method' => 'post',
                    'confirm' => 'Delete "' . $p['name'] . '" permanently? To hide it from the catalog instead, edit it and untick Active.'],
            ],
            'empty' => 'No products match.',
        ]),
        pagination(['page' => $d['page'], 'total_pages' => $d['pages'], 'base_url' => '/admin/products' . ($query ? '?' . http_build_query($query) : '')]),
    ], 'Products');
}

function admin_product_form(array $d): Response
{
    $product = $d['product'];
    $v = $d['values'];
    $errors = $d['errors'];
    $action = $product ? '/admin/products/' . $product['id'] . '/edit' : '/admin/products/create';

    return admin_page($product ? 'Edit product' : 'Add product', [
        section_title([
            'title' => $product ? 'Edit ' . $product['name'] : 'Add product',
            'subtitle' => $product ? 'Public address: /product/' . $product['slug'] : 'The web address is created from the name and never changes.',
            'size' => 'lg',
            'action' => $product && $product['is_active'] ? ['View on site', '/product/' . $product['slug']] : null,
        ]),
        $errors ? alert(['type' => 'danger', 'message' => 'Please fix the highlighted fields.']) : '',
        form_open(['action' => $action, 'multipart' => true]),
        row([
            col([
                input(['name' => 'name', 'label' => 'Name', 'value' => $v['name'] ?? '', 'required' => true, 'maxlength' => 150, 'error' => $errors['name'] ?? null]),
                select(['name' => 'category_id', 'label' => 'Category', 'options' => $d['categories'], 'selected' => $v['category_id'] ?? '',
                    'placeholder' => 'Choose a category', 'required' => true, 'error' => $errors['category_id'] ?? null]),
                textarea(['name' => 'description', 'label' => 'Description', 'rows' => 6, 'value' => $v['description'] ?? '',
                    'maxlength' => 5000, 'error' => $errors['description'] ?? null]),
                row([
                    col([input(['name' => 'wood_type', 'label' => 'Wood type', 'value' => $v['wood_type'] ?? '', 'maxlength' => 50,
                        'placeholder' => 'Narra', 'error' => $errors['wood_type'] ?? null])], 4),
                    col([input(['name' => 'dimensions', 'label' => 'Dimensions', 'value' => $v['dimensions'] ?? '', 'maxlength' => 100,
                        'placeholder' => '240 × 100 × 76 cm', 'error' => $errors['dimensions'] ?? null])], 4),
                    col([input(['name' => 'price', 'label' => 'Price (₱)', 'type' => 'number', 'step' => '0.01', 'min' => '0',
                        'value' => $v['price'] ?? '', 'help' => 'Leave blank for "Price upon request".', 'error' => $errors['price'] ?? null])], 4),
                ], ['gap' => 'sm']),
            ], 8),
            col([
                file_input(['name' => 'image', 'label' => 'Photo', 'accept' => 'image/jpeg,image/png,image/webp',
                    'current' => $product ? product_image($product) : null,
                    'help' => 'JPG, PNG or WebP, up to 2 MB.' . ($product ? ' Leave empty to keep the current photo.' : ''),
                    'error' => $errors['image'] ?? null]),
                checkbox(['name' => 'in_stock', 'label' => 'In stock (ready to buy)', 'checked' => !empty($v['in_stock'])]),
                checkbox(['name' => 'is_active', 'label' => 'Active (visible on the public site)', 'checked' => !empty($v['is_active'])]),
            ], 4),
        ]),
        submit(['label' => $product ? 'Save changes' : 'Create product', 'cancel' => ['Cancel', '/admin/products']]),
        form_close(),
    ], 'Products', $errors ? 422 : 200);
}

// ---------------------------------------------------------------- categories

function admin_categories(array $d): Response
{
    return admin_page('Categories', [
        section_title(['title' => 'Categories', 'size' => 'lg']),
        row([
            col([data_table([
                'columns' => ['name' => ['Name', 'strong'], 'slug' => 'Web address', 'product_count' => 'Products'],
                'rows' => array_map(fn (array $c) => ['slug' => '/catalog/' . $c['slug']] + $c, $d['categories']),
                'actions' => fn (array $c) => [[
                    'Delete', '/admin/categories/' . $c['id'] . '/delete', 'method' => 'post',
                    'confirm' => 'Delete the category "' . $c['name'] . '"?',
                ]],
                'empty' => 'No categories yet. Add the first one.',
            ])], 8),
            col([
                section_title(['title' => 'Add category', 'size' => 'sm']),
                form_open(['action' => '/admin/categories']),
                input(['name' => 'name', 'label' => 'Name', 'value' => $d['values']['name'] ?? '', 'required' => true,
                    'maxlength' => 100, 'error' => $d['errors']['name'] ?? null]),
                submit(['label' => 'Add category']),
                form_close(),
            ], 4),
        ]),
    ], 'Categories', $d['errors'] ? 422 : 200);
}

// ---------------------------------------------------------------- inquiries

function admin_inquiries(array $d): Response
{
    $pills = ['All' => '/admin/inquiries'];
    foreach (config('statuses') as $value => $status) {
        $pills[$status['label']] = '/admin/inquiries?status=' . $value;
    }
    $active = $d['status'] === '' ? 'All' : (count($d['selected']) === 1 ? status_label($d['selected'][0]) : '');
    $query = array_filter(['status' => $d['status'], 'q' => $d['q']], fn ($v) => $v !== '');
    $subtitle = $d['total'] . ' found' . (count($d['selected']) > 1 ? ' with status ' . implode(' or ', array_map('status_label', $d['selected'])) : '');

    return admin_page('Inquiries', [
        section_title(['title' => 'Inquiries', 'subtitle' => $subtitle, 'size' => 'lg']),
        pills(['items' => $pills, 'active' => $active]),
        search_form(['action' => '/admin/inquiries', 'value' => $d['q'], 'placeholder' => 'Search reference, name or phone',
            'hidden' => ['status' => $d['status']]]),
        inquiry_table($d['inquiries'], 'No inquiries match.'),
        pagination(['page' => $d['page'], 'total_pages' => $d['pages'], 'base_url' => '/admin/inquiries' . ($query ? '?' . http_build_query($query) : '')]),
    ], 'Inquiries');
}

function admin_inquiry(array $d): Response
{
    $i = $d['inquiry'];
    $v = $d['values'];
    $errors = $d['errors'];

    return admin_page($i['reference_no'], [
        section_title([
            'title' => 'Inquiry ' . $i['reference_no'],
            'subtitle' => 'Received ' . date('M j, Y g:i A', (int) strtotime($i['created_at']))
                . ' · Last updated ' . date('M j, Y g:i A', (int) strtotime($i['updated_at'])),
            'size' => 'lg',
            'action' => ['Back to inquiries', '/admin/inquiries'],
        ]),
        $errors ? alert(['type' => 'danger', 'message' => 'Please fix the highlighted fields.']) : '',
        row([
            col([
                section_title(['title' => 'From the customer', 'size' => 'sm']),
                badge(['text' => status_label($i['status']), 'color' => status_color($i['status'])]),
                definition_list(['items' => [
                    'Reference' => $i['reference_no'],
                    'Customer' => $i['customer_name'],
                    'Phone' => $i['phone'],
                    'Email' => $i['email'] ?: '—',
                    'Product' => $i['product_name'] ?? 'General inquiry',
                    'Listed price' => $i['product_name'] !== null ? money($i['product_price']) : null,
                    'Message' => $i['message'],
                ]]),
            ], 6),
            col([
                section_title(['title' => 'Staff update', 'size' => 'sm']),
                form_open(['action' => '/admin/inquiries/' . $i['id']]),
                status_select(['selected' => $v['status'], 'error' => $errors['status'] ?? null]),
                input(['name' => 'quoted_amount', 'label' => 'Quoted amount (₱)', 'type' => 'number', 'step' => '0.01', 'min' => '0',
                    'value' => $v['quoted_amount'], 'help' => 'Shown to the customer on the tracking page.', 'error' => $errors['quoted_amount'] ?? null]),
                textarea(['name' => 'notes', 'label' => 'Internal notes', 'rows' => 6, 'value' => $v['notes'], 'maxlength' => 5000,
                    'help' => 'Internal — not visible to the customer.', 'error' => $errors['notes'] ?? null]),
                submit(['label' => 'Save']),
                form_close(),
            ], 6),
        ]),
    ], 'Inquiries', $errors ? 422 : 200);
}

// ---------------------------------------------------------------- users

function admin_users(array $d): Response
{
    return admin_page('Users', [
        section_title(['title' => 'Staff accounts', 'size' => 'lg', 'action' => ['Add staff', '/admin/users/create']]),
        data_table([
            'columns' => ['name' => ['Name', 'strong'], 'email' => 'Email', 'role_badge' => ['Role', 'badge'], 'created_at' => ['Created', 'date']],
            'rows' => array_map(fn (array $u) => $u + [
                'role_badge' => $u['role'] === 'admin' ? ['text' => 'Admin', 'color' => 'primary'] : ['text' => 'Staff', 'color' => 'secondary'],
            ], $d['users']),
            'actions' => fn (array $u) => $u['id'] === $d['current_id'] ? [] : [[
                'Delete', '/admin/users/' . $u['id'] . '/delete', 'method' => 'post',
                'confirm' => 'Delete the account for ' . $u['name'] . '? They will no longer be able to sign in.',
            ]],
        ]),
    ], 'Users');
}

function admin_user_form(array $d): Response
{
    $v = $d['values'];
    $errors = $d['errors'];
    return admin_page('Add staff', [
        section_title(['title' => 'Add a staff account', 'size' => 'lg']),
        $errors ? alert(['type' => 'danger', 'message' => 'Please fix the highlighted fields.']) : '',
        row([col([
            form_open(['action' => '/admin/users/create']),
            input(['name' => 'name', 'label' => 'Name', 'value' => $v['name'] ?? '', 'required' => true, 'maxlength' => 100, 'error' => $errors['name'] ?? null]),
            input(['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $v['email'] ?? '', 'required' => true, 'maxlength' => 150,
                'error' => $errors['email'] ?? null]),
            input(['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'autocomplete' => 'new-password',
                'help' => 'At least 8 characters.', 'error' => $errors['password'] ?? null]),
            select(['name' => 'role', 'label' => 'Role', 'options' => ['staff' => 'Staff', 'admin' => 'Admin'], 'selected' => $v['role'] ?? 'staff',
                'required' => true, 'error' => $errors['role'] ?? null]),
            submit(['label' => 'Create account', 'cancel' => ['Cancel', '/admin/users']]),
            form_close(),
        ], 6)]),
    ], 'Users', $errors ? 422 : 200);
}
