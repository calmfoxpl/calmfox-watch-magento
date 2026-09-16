<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Backend\Model\View\Result\PageFactory;

/**
 * Ekran Calmfox Watch w panelu Magento. Renderowanie jest w bloku, tutaj
 * zostaje jedno zadanie poza wyświetleniem: dokończenie łączenia przez panel.
 *
 * Powrót z panelu przychodzi zwykłymi parametrami ?cw_token=…&cw_state=…
 * (te same nazwy co we wtyczce WordPressa i pakiecie Neosa, bo panel jest
 * jeden). Kolejność sprawdzeń nie jest przypadkowa: najpierw znacznik
 * jednorazowy, zużywany niezależnie od wyniku, potem format klucza, dopiero
 * na końcu rozmowa z hubem. Klucz jest jawny, więc to znacznik decyduje,
 * czy ten powrót w ogóle nas dotyczy.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Calmfox_Watch::watch';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly SecretManager $secrets,
        private readonly HubClient $hub,
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $token = trim((string) $this->getRequest()->getParam('cw_token', ''));
        $state = trim((string) $this->getRequest()->getParam('cw_state', ''));
        if ('' !== $token || '' !== $state) {
            $this->finishConnect($token, $state);

            // Przekierowanie czyści adres z klucza instalacyjnego: nie ma powodu,
            // żeby został w historii przeglądarki i w dziennikach serwera.
            /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
            $redirect = $this->resultRedirectFactory->create();

            return $redirect->setPath('calmfox_watch/watch/index');
        }

        // Fabryka strony z przestrzeni Backend, nie Framework: tylko wynik
        // backendowy zna setActiveMenu(), czyli podświetlenie pozycji w menu.
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Calmfox_Watch::watch');
        $page->getConfig()->getTitle()->prepend(__('Calmfox Watch'));

        return $page;
    }

    private function finishConnect(string $token, string $state): void
    {
        if (!$this->secrets->consumeConnectState($state)) {
            $this->messageManager->addErrorMessage(__('Znacznik połączenia wygasł albo się nie zgadza. Kliknij „Połącz przez Calmfox Watch" jeszcze raz.'));

            return;
        }
        if (1 !== preg_match('/^(fxp_live_)?[a-f0-9]{16,32}$/', $token)) {
            $this->messageManager->addErrorMessage(__('Klucz z panelu ma nieoczekiwany format, spróbuj połączyć jeszcze raz.'));

            return;
        }

        $result = $this->hub->pair($token);
        if ($result['ok']) {
            $this->messageManager->addSuccessMessage($result['message']);

            return;
        }
        $this->messageManager->addErrorMessage($result['message']);
    }
}
