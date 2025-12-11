<?php
class BaseController
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    protected function render(string $view, array $data = []): void
    {
        extract($data);
        $config = $this->config;
        include __DIR__ . '/../Views/layout.php';
    }
}
