<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Wspólna część operacji z ekranu: uprawnienie i znacznik formularza.
 *
 * Znacznik sprawdzamy sami, mimo że panel Magento robi to również po swojej
 * stronie: te operacje zmieniają stan połączenia z monitoringiem (a jedna
 * z nich wymienia sekret adresu kontrolnego), więc wolimy mieć tu jawny
 * warunek, który widać w kodzie, niż polegać wyłącznie na warstwie wyżej.
 */
abstract class AbstractWatchAction extends Action
{
    /** Zasób z etc/acl.xml: bez niego akcja nie wykona się nawet dla zalogowanego administratora. */
    public const ADMIN_RESOURCE = 'Calmfox_Watch::watch';

    public function __construct(
        Context $context,
        private readonly FormKeyValidator $formKeyValidator,
    ) {
        parent::__construct($context);
    }

    /**
     * Zwraca przekierowanie, gdy znacznik formularza się nie zgadza, i null,
     * gdy wszystko jest w porządku. Świadomie nie rzucamy wyjątkiem: skończyłby
     * się stroną błędu, a to jest sytuacja, z której administrator ma wyjść
     * jednym kliknięciem (najczęściej po prostu wygasła sesja).
     */
    protected function rejectInvalidFormKey(): ?Redirect
    {
        if ($this->formKeyValidator->validate($this->getRequest())) {
            return null;
        }
        $this->messageManager->addErrorMessage(__('Nieprawidłowy znacznik formularza. Odśwież ekran i spróbuj ponownie.'));

        return $this->back();
    }

    protected function back(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        return $redirect->setPath('calmfox_watch/watch/index');
    }

    /** Wspólna obsługa: wynik z huba w postaci {ok, message} na komunikat panelu. */
    protected function report(bool $ok, string $message): void
    {
        if ($ok) {
            $this->messageManager->addSuccessMessage($message);

            return;
        }
        $this->messageManager->addErrorMessage($message);
    }
}
