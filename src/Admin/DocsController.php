<?php

declare(strict_types=1);

namespace Puwit\Admin;

use Puwit\Http\OpenApiGenerator;
use Puwit\Http\Request;
use Puwit\Http\Response;

class DocsController
{
    public function __construct(private OpenApiGenerator $generator) {}

    public function openapi(Request $request): Response
    {
        return Response::json($this->generator->generate());
    }

    public function docs(Request $request): Response
    {
        return Response::html(<<<'HTML'
<!DOCTYPE html>
<html>
<head><title>PUWIT API</title><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<body>
<script id="api-reference"></script>
<script>document.currentScript.previousElementSibling.dataset.url=new URL('openapi.json',location.href).href</script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
HTML);
    }
}
