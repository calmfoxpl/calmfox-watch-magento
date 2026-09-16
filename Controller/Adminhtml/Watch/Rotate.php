<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Wymiana sekretu adresu kontrolnego: nowy działa od razu, a hub dostaje nowy
 * adres tą samą drogą co przy parowaniu. Poprzedni sekret jest honorowany
 * jeszcze kwadrans, więc nieudane przepięcie nie zrywa monitoringu.
 */
class Rotate extends AbstractWatchAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        private readonly SecretManager $secrets,
        private readonly HubClient $hub,
    ) {
        parent::__construct($context, $formKeyValidator);
    }

    public function execute(): Redirect
    {
        if (null !== ($rejected = $this->rejectInvalidFormKey())) {
            return $rejected;
        }

        $this->secrets->rotate();
        $result = $this->hub->pair();

        if ($result['ok']) {
            $this->messageManager->addSuccessMessage(__('Klucz zabezpieczający wymieniony. Monitoring korzysta już z nowego adresu.'));

            return $this->back();
        }

        $this->messageManager->addWarningMessage(__(
            'Klucz wymieniono w sklepie, ale nie udało się zaktualizować go w panelu: %1 Poprzedni klucz działa jeszcze 15 minut, spróbuj ponownie przyciskiem „Połącz ponownie".',
            $result['message']
        ));

        return $this->back();
    }
}
