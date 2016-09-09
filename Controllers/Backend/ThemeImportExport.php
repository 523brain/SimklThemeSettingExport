<?php
/**
 * Import / export plugin for theme configurations
 *
 * @category   Shopware
 * @package    Shopware\Plugins\SimklThemeSettingExport
 * @author     Simon Klimek <me@simonklimek.de>
 * @copyright  2015 Simon Klimek ( http://simonklimek.de )
 * @license    http://www.gnu.org/licenses/agpl-3.0.en.html GNU AFFERO GENERAL PUBLIC LICENSE
 */

use Symfony\Component\HttpFoundation\FileBag,
    Symfony\Component\HttpFoundation\File\Exception\FileException;

class Shopware_Controllers_Backend_ThemeImportExport extends Shopware_Controllers_Backend_ExtJs {

    const UPLOADED_CONFIG_FILENAME = 'simklithemesetting_upload.theme';

    /**
     * @var \Shopware\SimklThemeSettingExport\Components\ThemeImportExportService
     */
    private $service = null;

    private function getService() {
        if ($this->service == null) {
            $this->service = $this->get('simklthemeimportexport.theme_import_export_service');
        }
        return $this->service;
    }

    private function getThemeById($themeId) {
        $em = $this->get('models');
        $tplRepo = $em->getRepository('Shopware\Models\Shop\Template');
        return  $tplRepo->find($themeId);
    }

    private function getShopById($shopId) {
        $em = $this->get('models');
        $shopRepo = $em->getRepository('Shopware\Models\Shop\Shop');
        return $shopRepo->find($shopId);
    }

	public function exportAction() {
		$theme = $this->getThemeById($this->Request()->get('theme'));
        $shop = $this->getShopById($this->Request()->get('shop'));

        if ($theme == null || $shop == null) {
            $this->View()->assign([
                'success' => false, 'message' => 'parameter missing'
            ]);
            return;
        }

        $service = $this->getService();

        $settings = $service->getThemeSettingsArray($theme,$shop);

        // provide theme settings as file
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $this->Front()->Plugins()->Json()->setRenderer(false);

        $filename = $theme->getTemplate() . '-' . $shop->getName() . '-' . date('Y-m-d-H-i') . '.theme';
        $content = serialize($settings);


        $response = $this->Response();        
        $headers = $response->getHeaders();
        $response->setHeader('Cache-Control', 'public')
                ->setHeader('Content-Type', 'text/plain')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setHeader('Content-Length', strlen($content));
    
        echo $content;

	}

    public function importAction() {
        $theme = $this->getThemeById($this->Request()->get('theme'));
        $shop = $this->getShopById($this->Request()->get('shop'));
        $this->View()->assign(
            $this->handleUploadedConfig($theme,$shop)
        );
    }

    private function handleUploadedConfig($theme,$shop) {
        $cacheDir = $this->container->getParameter('kernel.cache_dir');
        $fullpath = $cacheDir . DIRECTORY_SEPARATOR . $this::UPLOADED_CONFIG_FILENAME;
        $service = $this->getService();

        if ($theme == null || $shop == null) {
            return [ 'success' => false, 'message' => 'parameters missing'];
        }

        try {
            $file = (new FileBag($_FILES))->get('theme');
        } catch (InvalidArgumentException $e) {
            return [ 'success' => false, 'message' => 'no configuration file uplaoded' ];
        }
        try {
            $file->move($cacheDir, $this::UPLOADED_CONFIG_FILENAME);
        } catch (FileException $e) {
            return [
                'success' => false,
                'message' => 'Could not save file. ' . $e->getMessage()
            ];
        }
        $config = unserialize(file_get_contents($fullpath));

        if ($config === false) {
            return ['success' => false, 'message' => 'illegal configuration uploaded'];
        }

        $service->setThemeSettingsArray($theme, $shop, $config);
        return ['success' => true];
    }

}