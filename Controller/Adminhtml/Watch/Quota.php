<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Model\PayloadProvider;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Limit dyskowy konta podany przez klienta. Hosting współdzielony go nie
 * ujawnia (disk_free_space pokazuje cały wolumen serwera), więc jedyną
 * prawdziwą liczbę ma klient: z panelu hostingu albo z umowy. Zero znaczy
 * „nie znam limitu" i tak też opisujemy check, zamiast zgadywać.
 */
class Quota extends AbstractWatchAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        private readonly StateStore $state,
        private readonly PayloadProvider $payloads,
    ) {
        parent::__construct($context, $formKeyValidator);
    }

    public function execute(): Redirect
    {
        if (null !== ($rejected = $this->rejectInvalidFormKey())) {
            return $rejected;
        }

        $raw = str_replace(',', '.', trim((string) $this->getRequest()->getParam('quota', '')));
        $quota = '' === $raw ? 0.0 : (float) $raw;
        if ($quota < 0 || $quota > 100000) {
            $this->messageManager->addErrorMessage(__('Podaj limit w gigabajtach, liczbę z zakresu od 0 do 100000 (0 znaczy „nie znam limitu").'));

            return $this->back();
        }

        $this->state->set(['diskQuotaGb' => $quota]);
        $this->payloads->forget();

        $this->messageManager->addSuccessMessage($quota > 0
            ? __('Zapisane. Pilnujemy zajętości względem %1 GB.', rtrim(rtrim(number_format($quota, 2, ',', ' '), '0'), ','))
            : __('Wyczyszczone. Wracamy do informowania, że limit konta nie jest znany.'));

        return $this->back();
    }
}
