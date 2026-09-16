<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Aktywacja pakietu Free. Zgoda jest wymagana świadomie: wysyłamy do Calmfox
 * adres e-mail i domenę, więc pytamy o to wprost, a nie drobnym drukiem
 * pod przyciskiem.
 */
class Register extends AbstractWatchAction implements HttpPostActionInterface
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

        $email = trim((string) $this->getRequest()->getParam('email', ''));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Podaj poprawny adres e-mail.'));

            return $this->back();
        }
        if (!$this->getRequest()->getParam('consent')) {
            $this->messageManager->addErrorMessage(__('Do aktywacji potrzebna jest zgoda na przekazanie adresu e-mail i domeny do Calmfox.'));

            return $this->back();
        }

        $result = $this->hub->register($email);
        $this->report($result['ok'], $result['message']);

        return $this->back();
    }
}
