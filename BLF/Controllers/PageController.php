<?php

declare(strict_types=1);

namespace App\Controllers;

use Lightpull\Response;

final class PageController
{
    public function about(): Response
    {
        return page_about();
    }

    public function contact(): Response
    {
        return page_contact();
    }
}
