<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Świadome zakończenie monitoringu wnętrza. Mówimy hubowi wprost, zamiast
 * zostawiać mu milczący adres: cisza jest dla niego sygnałem awarii i po
 * trzech nieudanych odpytaniach otworzyłby incydent.
 */
class Disconnect extends AbstractWatchAction implements HttpPostActionInterface
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

        $result = $this->hub->disconnect();
        if ($result['ok']) {
            $this->messageManager->addSuccessMessage($result['message']);

            return $this->back();
        }

        $this->messageManager->addWarningMessage(__('Monitoring wstrzymany w sklepie, ale panel nie potwierdził rozłączenia: %1', $result['message']));

        return $this->back();
    }
}
