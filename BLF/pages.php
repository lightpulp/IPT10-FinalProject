<?php

declare(strict_types=1);

/*
 * Public page builders for Betis Legacy Furniture. Each one composes Lightpull
 * components from ui.php and returns a Response; no HTML is written here.
 */

use Lightpull\Response;

// ---------------------------------------------------------------- site chrome

function site_navbar(string $active = ''): string
{
    return navbar([
        'brand' => 'Betis Legacy Furniture',
        'brand_url' => '/',
        'color' => 'dark',
        'size' => 'md',
        'links' => ['Home' => '/', 'Catalog' => '/catalog', 'About' => '/about', 'Contact' => '/contact'],
        'active' => $active,
        'right' => ['Track my request' => '/track'],
    ]);
}

function site_footer(): string
{
    return footer([
        'columns' => [
            'Betis Legacy Furniture' => [
                'Solid wood furniture, made by hand in Pampanga.' => null,
                'About us' => '/about',
            ],
            'Workshops' => [
                'Minalin, Pampanga' => '/contact',
                'Guagua, Pampanga' => '/contact',
            ],
            'Customers' => [
                'Browse the catalog' => '/catalog',
                'Request a quote' => '/inquire',
                'Track my request' => '/track',
            ],
        ],
        'text' => '© ' . date('Y') . ' Betis Legacy Furniture',
    ]);
}

function site_page(string $title, string $active, array $parts, array $o = []): Response
{
    return page($title, [site_navbar($active), container($parts), site_footer()], $o);
}

// ---------------------------------------------------------------- catalog pieces

function product_image(array $product): ?string
{
    return empty($product['image']) ? null : '/uploads/' . $product['image'];
}

function stock_badge(array $product): array
{
    return $product['in_stock']
        ? ['text' => 'In stock', 'color' => 'success']
        : ['text' => 'Made to order', 'color' => 'secondary'];
}

function product_grid(array $products, int $columns = 3): string
{
    return card_grid($products, [
        'title' => 'name',
        'subtitle' => 'wood_type',
        'text' => fn (array $p) => money($p['price']),
        'image' => fn (array $p) => product_image($p),
        'link' => fn (array $p) => '/product/' . $p['slug'],
        'badge' => fn (array $p) => stock_badge($p),
    ], ['columns' => $columns]);
}

function category_strip(array $categories): string
{
    return card_grid($categories, [
        'title' => 'name',
        'text' => fn (array $c) => $c['product_count'] . ($c['product_count'] === 1 ? ' piece' : ' pieces'),
        'link' => fn (array $c) => '/catalog/' . $c['slug'],
    ], ['columns' => 6]);
}

// ---------------------------------------------------------------- pages

function page_home(array $d): Response
{
    return page('Solid wood furniture from Pampanga', [
        site_navbar('Home'),
        hero([
            'title' => 'Solid wood furniture, built to outlast us',
            'text' => 'Hand-built in Minalin and Guagua, Pampanga since 1978.',
            'button' => ['Browse the catalog', '/catalog'],
        ]),
        container([
            section_title(['title' => 'Featured pieces', 'subtitle' => 'Recently finished in our workshops.', 'action' => ['View all', '/catalog']]),
            $d['products'] ? product_grid($d['products']) : empty_state(['message' => 'New pieces are on their way.']),
            section_title(['title' => 'Shop by category']),
            category_strip($d['categories']),
            section_title(['title' => 'Two workshops, one family of craftsmen']),
            row([
                col([text_block([
                    'text' => "Every piece is cut, joined and finished by hand at our workshops in Minalin and Guagua. "
                        . "We work in narra, mahogany, acacia, mango wood and kamagong, and most of the catalog can be made to your size.\n\n"
                        . "Not sure what you need? Send us a message with your measurements and we will help you choose.",
                ])], 8),
                col([
                    button(['label' => 'Visit our workshops', 'url' => '/contact', 'outline' => true]),
                    ' ',
                    button(['label' => 'Ask a question', 'url' => '/inquire']),
                ], 4),
            ]),
        ]),
        site_footer(),
    ]);
}

function page_catalog(array $d): Response
{
    $category = $d['category'];
    $title = $category['name'] ?? 'Catalog';
    $base = $category ? '/catalog/' . $category['slug'] : '/catalog';
    $pills = ['All' => '/catalog'];
    foreach ($d['categories'] as $c) {
        $pills[$c['name']] = '/catalog/' . $c['slug'];
    }
    $count = $d['total'] . ($d['total'] === 1 ? ' piece' : ' pieces') . ($d['q'] !== '' ? ' matching “' . $d['q'] . '”' : '');

    return site_page($title, 'Catalog', [
        breadcrumbs(['items' => $category ? ['Home' => '/', 'Catalog' => '/catalog', $title => null] : ['Home' => '/', 'Catalog' => null]]),
        section_title(['title' => $title, 'subtitle' => $count, 'size' => 'lg']),
        pills(['items' => $pills, 'active' => $category['name'] ?? 'All']),
        search_form(['action' => $base, 'value' => $d['q'], 'placeholder' => 'Search by name or description']),
        $d['products']
            ? product_grid($d['products'])
            : empty_state([
                'message' => $d['q'] !== '' ? 'No pieces match your search.' : 'There are no pieces in this category yet.',
                'action' => ['View the full catalog', '/catalog'],
            ]),
        pagination([
            'page' => $d['page'],
            'total_pages' => $d['pages'],
            'base_url' => $d['q'] !== '' ? $base . '?' . http_build_query(['q' => $d['q']]) : $base,
        ]),
    ]);
}

function page_product(array $d): Response
{
    $p = $d['product'];
    return site_page($p['name'], 'Catalog', [
        breadcrumbs(['items' => [
            'Home' => '/',
            'Catalog' => '/catalog',
            $p['category_name'] => '/catalog/' . $p['category_slug'],
            $p['name'] => null,
        ]]),
        row([
            col([image(['src' => product_image($p), 'alt' => $p['name'], 'height' => 440])], 7),
            col([
                badge(stock_badge($p)),
                section_title(['title' => $p['name'], 'size' => 'lg']),
                text_block(['text' => money($p['price']), 'lead' => true]),
                text_block(['text' => (string) $p['description']]),
                definition_list(['items' => [
                    'Wood type' => $p['wood_type'],
                    'Dimensions' => $p['dimensions'],
                    'Category' => $p['category_name'],
                    'Availability' => $p['in_stock'] ? 'Ready stock' : 'Made to order',
                ]]),
                button(['label' => 'Request a quote for this piece', 'url' => '/inquire/' . $p['slug'], 'size' => 'lg']),
            ], 5),
        ]),
        $d['related'] ? section_title(['title' => 'More ' . $p['category_name']]) : '',
        $d['related'] ? product_grid($d['related'], 4) : '',
    ]);
}

function page_inquiry_form(array $d): Response
{
    $v = $d['values'];
    $errors = $d['errors'];
    $product = $d['product'];

    $productField = $product
        ? input(['id' => 'f-product-name', 'label' => 'Product', 'value' => $product['name'], 'readonly' => true])
            . input(['type' => 'hidden', 'name' => 'product_id', 'value' => $product['id']])
            . input(['type' => 'hidden', 'name' => 'lock_product', 'value' => '1'])
        : select([
            'name' => 'product_id',
            'label' => 'Product',
            'placeholder' => 'General inquiry',
            'options' => $d['products'],
            'selected' => $v['product_id'] ?? '',
            'error' => $errors['product_id'] ?? null,
        ]);

    return site_page($product ? 'Request a quote' : 'Send an inquiry', '', [
        section_title([
            'title' => $product ? 'Request a quote' : 'Send us an inquiry',
            'subtitle' => $product ? 'For the ' . $product['name'] . '.' : 'Ask about custom sizes, wood types, or anything else.',
            'size' => 'lg',
        ]),
        $errors ? alert(['type' => 'danger', 'message' => 'Please fix the highlighted fields and send again.']) : '',
        row([
            col([
                form_open(['action' => '/inquire']),
                honeypot(['name' => 'website']),
                input(['name' => 'customer_name', 'label' => 'Full name', 'value' => $v['customer_name'] ?? '', 'required' => true,
                    'maxlength' => 100, 'autocomplete' => 'name', 'error' => $errors['customer_name'] ?? null]),
                input(['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'value' => $v['phone'] ?? '', 'required' => true,
                    'maxlength' => 30, 'autocomplete' => 'tel', 'placeholder' => '0917 123 4567',
                    'help' => 'We will call or text you on this number. You also need it to track your request.',
                    'error' => $errors['phone'] ?? null]),
                input(['name' => 'email', 'label' => 'Email (optional)', 'type' => 'email', 'value' => $v['email'] ?? '',
                    'maxlength' => 150, 'autocomplete' => 'email', 'error' => $errors['email'] ?? null]),
                $productField,
                textarea(['name' => 'message', 'label' => 'Message', 'rows' => 6, 'value' => $v['message'] ?? '', 'required' => true,
                    'maxlength' => 2000, 'placeholder' => 'Tell us the size you need, your preferred wood, and how many pieces.',
                    'error' => $errors['message'] ?? null]),
                submit(['label' => 'Send request', 'cancel' => ['Back to catalog', '/catalog']]),
                form_close(),
            ], 8),
            col([
                section_title(['title' => 'What happens next', 'size' => 'sm']),
                text_block(['text' => "You will get a reference number as soon as you send this form.\n\n"
                    . "Our staff usually reply within one or two business days by phone, text, or email.\n\n"
                    . "Use your reference number and phone number on the Track my request page to see where things stand.",
                    'muted' => true]),
            ], 4),
        ]),
    ], ['status' => $errors ? 422 : 200]);
}

function page_inquiry_sent(array $d): Response
{
    return site_page('Request sent', '', [
        section_title(['title' => 'Thank you, we have your request', 'size' => 'lg']),
        callout([
            'label' => 'Your reference number',
            'value' => $d['reference'],
            'text' => 'Our staff typically respond within one or two business days.',
        ]),
        text_block(['text' => 'Keep this reference number and the phone number you used. You need both to track your request.']),
        button(['label' => 'Track my request', 'url' => '/track']),
        ' ',
        button(['label' => 'Continue browsing', 'url' => '/catalog', 'outline' => true]),
    ]);
}

function page_track(array $d): Response
{
    return site_page('Track my request', '', [
        section_title([
            'title' => 'Track my request',
            'subtitle' => 'Enter the reference number you received and the phone number you used.',
            'size' => 'lg',
        ]),
        row([
            col([
                form_open(['action' => '/track']),
                input(['name' => 'reference', 'label' => 'Reference number', 'value' => $d['values']['reference'],
                    'placeholder' => 'BLF-2026-0001', 'required' => true, 'maxlength' => 20]),
                input(['name' => 'phone', 'label' => 'Phone number', 'type' => 'tel', 'value' => $d['values']['phone'],
                    'required' => true, 'maxlength' => 30]),
                submit(['label' => 'Check status']),
                form_close(),
            ], 5),
            col([
                $d['not_found'] ? alert(['type' => 'warning', 'message' => 'We couldn\'t find a request with those details. Check both and try again.']) : '',
                $d['result'] ? track_result($d['result']) : '',
            ], 7),
        ]),
    ]);
}

function track_result(array $r): string
{
    $statuses = config('statuses');
    $steps = array_diff_key($statuses, ['cancelled' => true]);
    $progress = $r['status'] === 'cancelled'
        ? alert(['type' => 'danger', 'message' => 'This request was cancelled. Contact us if you would like to reopen it.'])
        : progress_steps([
            'steps' => array_column($steps, 'label'),
            'current' => (int) array_search($r['status'], array_keys($steps), true),
        ]);

    return section_title(['title' => $r['reference_no'], 'size' => 'sm'])
        . badge(['text' => status_label($r['status']), 'color' => status_color($r['status'])])
        . $progress
        . definition_list(['items' => [
            'Product' => $r['product_name'] ?? 'General inquiry',
            'Submitted' => date('F j, Y', (int) strtotime($r['created_at'])),
            'Status' => status_label($r['status']),
            'Quoted amount' => $r['quoted_amount'] !== null ? money($r['quoted_amount']) : null,
        ]]);
}

function page_about(): Response
{
    return site_page('About us', 'About', [
        section_title(['title' => 'About Betis Legacy Furniture', 'size' => 'lg']),
        row([
            col([
                text_block(['lead' => true, 'text' => 'We are a family of furniture makers from Pampanga, '
                    . 'building solid wood pieces meant to be handed down.']),
                text_block(['text' => "Betis Legacy Furniture started as a small carpentry shop and grew into two workshops, one in Minalin "
                    . "and one in Guagua. The name honours Betis, the Guagua district long known for its woodcarvers.\n\n"
                    . "We still build the way we started: solid wood, traditional joinery, and finishes applied by hand. "
                    . "Most of what we make is made to order, so a table can fit your dining room and a door can fit your doorway.\n\n"
                    . "When you send an inquiry, a real person from the workshop reads it and gets back to you."]),
            ], 8),
            col([row([
                col([card(['title' => 'Our woods', 'text' => 'Narra, mahogany, acacia, mango wood and kamagong, chosen board by board.'])]),
                col([card(['title' => 'Made to order', 'text' => 'Tell us the size, wood and finish you want and we will quote it.', 'link' => '/inquire'])]),
            ])], 4),
        ]),
    ]);
}

function page_contact(): Response
{
    $workshops = [
        'Minalin workshop' => [
            'Address' => "Workshop Road, Minalin\nPampanga 2019",
            'Phone' => '+63 917 000 0101',
            'Hours' => "Monday to Saturday, 8:00 AM – 5:00 PM\nClosed Sundays and holidays",
        ],
        'Guagua workshop' => [
            'Address' => "Workshop Road, Betis, Guagua\nPampanga 2003",
            'Phone' => '+63 917 000 0202',
            'Hours' => "Monday to Saturday, 8:00 AM – 5:00 PM\nClosed Sundays and holidays",
        ],
    ];
    $columns = [];
    foreach ($workshops as $name => $details) {
        $columns[] = col([section_title(['title' => $name, 'size' => 'sm']), definition_list(['items' => $details])], 6);
    }

    return site_page('Contact', 'Contact', [
        section_title(['title' => 'Visit or call us', 'size' => 'lg']),
        text_block(['lead' => true, 'text' => 'Both workshops are in Pampanga, and visits are welcome. '
            . 'Come and see the wood, the work in progress, and finished pieces.']),
        row($columns),
        section_title(['title' => 'Prefer to write?', 'size' => 'sm']),
        text_block(['text' => 'Send us an inquiry and we will get back to you within one or two business days.']),
        button(['label' => 'Send an inquiry', 'url' => '/inquire']),
    ]);
}

function page_error(int $status, string $message, ?string $detail = null): Response
{
    $titles = [403 => 'Access denied', 404 => 'Page not found', 419 => 'Session expired', 500 => 'Something went wrong'];
    return site_page($titles[$status] ?? 'Error', '', [
        section_title(['title' => $titles[$status] ?? 'Error', 'subtitle' => 'Error ' . $status, 'size' => 'lg']),
        text_block(['text' => $message, 'lead' => true]),
        button(['label' => 'Go to the home page', 'url' => '/']),
        ' ',
        button(['label' => 'Browse the catalog', 'url' => '/catalog', 'outline' => true]),
        $detail !== null ? section_title(['title' => 'Debug details', 'subtitle' => 'Shown because debug is on.', 'size' => 'sm']) : '',
        $detail !== null ? code_block(['text' => $detail]) : '',
    ], ['status' => $status]);
}
