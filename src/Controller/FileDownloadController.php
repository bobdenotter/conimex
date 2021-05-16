<?php

declare(strict_types=1);

namespace BobdenOtter\Conimex\Controller;

use BobdenOtter\Conimex\Export;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Controller\Backend\BackendZoneInterface;
use Bolt\Security\ContentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FileDownloadController extends AbstractController implements BackendZoneInterface
{
    /** @var Export */
    private $exporter;

    /** @var Config */
    private $config;

    public function __construct(Export $exporter, Config $config)
    {
        $this->exporter = $exporter;
        $this->config = $config;
    }

    /**
     * @Route("/export/{format}/{contentType}", name="conimex_export_contenttype")
     */
    public function download(string $format, string $contentType): Response
    {
        $contentTypeObject = ContentType::factory($contentType, $this->config->get('contenttypes'));
        $this->denyAccessUnlessGranted(ContentVoter::CONTENT_MENU_LISTING, $contentTypeObject);

        $data = $this->exporter->export($contentType, $format);

        $filename = sprintf('%s.%s', $contentType, $format);

        return new Response($data, 200, [
            'X-Sendfile' => $filename,
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
