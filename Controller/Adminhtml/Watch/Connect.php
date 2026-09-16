<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Adminhtml\Watch;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Model\HubClient;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * Wyjście do panelu Calmfox Watch, ta sama droga co we wtyczce WordPressa:
 * użytkownik loguje się albo zakłada konto po naszej stronie, wybiera
 * organizację, a wraca tutaj z kluczem instalacyjnym i moduł paruje się sam.
 *
 * Adres powrotny to adres TEGO ekranu z panelu Magento, razem z kluczem
 * adresu panelu. Panel przyjmie go tylko wtedy, gdy host zgadza się z domeną
 * łączonego sklepu, a ścieżka niesie znacznik panelu tej platformy
 * (/calmfox_watch/). Dlatego przy panelu na osobnej domenie ta droga jest
 * niedostępna i mówimy o tym wprost na ekranie, zamiast wysyłać człowieka
 * w podróż zakończoną komunikatem o błędzie.
 */
class Connect extends AbstractWatchAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        FormKeyValidator $formKeyValidator,
        private readonly SecretManager $secrets,
        private readonly HubClient $hub,
        private readonly BackendUrl $backendUrl,
    ) {
        parent::__construct($context, $formKeyValidator);
    }

    public function execute(): Redirect
    {
        if (null !== ($rejected = $this->rejectInvalidFormKey())) {
            return $rejected;
        }

        $domain = $this->hub->domain();
        $return = $this->backendUrl->getUrl('calmfox_watch/watch/index');

        if ('' === $domain || !self::sameHost($return, $domain)) {
            $this->messageManager->addErrorMessage(__('Panel administracyjny stoi pod innym adresem niż sklep, więc bezpieczny powrót z Calmfox Watch nie jest możliwy. Połącz kluczem instalacyjnym z ekranu Integracje.'));

            return $this->back();
        }

        $query = http_build_query([
            'domain' => $domain,
            'return' => $return,
            'state' => $this->secrets->makeConnectState(),
        ]);

        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();

        return $redirect->setUrl($this->hub->panelUrl().'/polacz/magento?'.$query);
    }

    /** Apex i www to ta sama strona, jak wszędzie w Watchu. */
    private static function sameHost(string $url, string $domain): bool
    {
        $host = mb_strtolower((string) parse_url($url, \PHP_URL_HOST));

        return '' !== $host && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', mb_strtolower($domain));
    }
}
