<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Model\PayloadProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Przeliczenie sprawdzeń na żądanie. Sekcja bezpieczeństwa żyje w pamięci
 * podręcznej dziesięć minut, więc bez tego przycisku po naprawie usterki
 * ekran przez kwadrans pokazywałby stary wynik i człowiek naprawiałby coś,
 * co już działa.
 */
class Refresh extends AbstractWatchAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        private readonly PayloadProvider $payloads,
    ) {
        parent::__construct($context, $formKeyValidator);
    }

    public function execute(): Redirect
    {
        if (null !== ($rejected = $this->rejectInvalidFormKey())) {
            return $rejected;
        }

        $this->payloads->forget();
        $this->messageManager->addSuccessMessage(__('Sprawdzenia zostaną policzone od nowa.'));

        return $this->back();
    }
}
