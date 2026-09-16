<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/** Połączenie z istniejącą stroną w panelu kluczem instalacyjnym fxp_live_… */
class Pair extends AbstractWatchAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        private readonly HubClient $hub,
    ) {
        parent::__construct($context, $formKeyValidator);
    }

    public function execute(): Redirect
    {
        if (null !== ($rejected = $this->rejectInvalidFormKey())) {
            return $rejected;
        }

        $token = trim((string) $this->getRequest()->getParam('token', ''));
        if (1 !== preg_match('/^(fxp_live_)?[a-f0-9]{16,32}$/', $token)) {
            $this->messageManager->addErrorMessage(__('Klucz ma inny format niż fxp_live_… Skopiuj go z ekranu Integracje w panelu.'));

            return $this->back();
        }

        $result = $this->hub->pair($token);
        $this->report($result['ok'], $result['message']);

        return $this->back();
    }
}
