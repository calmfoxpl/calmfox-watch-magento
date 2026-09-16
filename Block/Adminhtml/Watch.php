<?php

declare(strict_types=1);

namespace Calmfox\Watch\Block\Adminhtml;

use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Core\PlanState;
use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Model\HubClient;
use Calmfox\Watch\Model\PayloadProvider;
use Calmfox\Watch\Model\UpdateHistory;
use Calmfox\Watch\Model\Version;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\Data\Form\FormKey;

/**
 * Dane ekranu Calmfox Watch w panelu Magento. Blok tylko zbiera to, co ma
 * pokazać szablon: żadnej logiki decyzyjnej, bo wszystkie odpowiedzi ma już
 * payload, który tak samo jedzie do huba. To, co widzi tu właściciel sklepu,
 * jest dokładnie tym, co zobaczy w panelu Calmfox.
 */
class Watch extends Template
{
    protected $_template = 'Calmfox_Watch::watch.phtml';

    public function __construct(
        Context $context,
        private readonly StateStore $state,
        private readonly SecretManager $secrets,
        private readonly HubClient $hub,
        private readonly PayloadProvider $payloads,
        private readonly UpdateHistory $history,
        private readonly FormKey $formKey,
        private readonly BackendUrl $backendUrl,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function getVersion(): string
    {
        return Version::NUMBER;
    }

    public function isConnected(): bool
    {
        return (bool) $this->state->get('connected', false);
    }

    /** @return array<string, mixed> */
    public function getState(): array
    {
        return $this->state->all();
    }

    public function getHealthUrl(): string
    {
        return $this->hub->healthUrl();
    }

    public function getDomain(): string
    {
        return $this->hub->domain();
    }

    public function getPanelUrl(): string
    {
        return $this->hub->panelUrl();
    }

    /** Adres tego sklepu w panelu Calmfox Watch, o ile znamy jego identyfikator. */
    public function getPanelSiteUrl(): string
    {
        return $this->hub->panelLink('/app/dashboard');
    }

    /** Pakiet z chwili połączenia. Źródłem prawdy jest panel i tak to nazywamy na ekranie. */
    public function getPlan(): string
    {
        return (string) $this->state->get('plan', '');
    }

    public function isFreePlan(): bool
    {
        return PlanState::isFree($this->getPlan());
    }

    /** Ekran wyboru pakietu w panelu, w kontekście tego sklepu. */
    public function getPlanUrl(): string
    {
        return $this->hub->panelLink('/app/plan');
    }

    /** @return array<string, mixed> */
    public function getHealth(): array
    {
        return $this->payloads->payload(PayloadProvider::SECTION_HEALTH);
    }

    /** @return array<string, mixed> */
    public function getSecurity(): array
    {
        return $this->payloads->payload(PayloadProvider::SECTION_SECURITY);
    }

    /** @return list<array<string, mixed>> */
    public function getHistory(): array
    {
        return \array_slice($this->history->all(), 0, 10);
    }

    /** @return array{ok: bool, message: string} */
    public function getLoopback(): array
    {
        return $this->hub->loopbackCheck();
    }

    public function getLastPollAt(): ?int
    {
        return $this->secrets->lastPollAt();
    }

    public function getDiskQuotaGb(): float
    {
        $stored = $this->state->get('diskQuotaGb', 0);

        return \is_numeric($stored) ? (float) $stored : 0.0;
    }

    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getActionUrl(string $action): string
    {
        return $this->getUrl('calmfox_watch/watch/'.$action);
    }

    /**
     * Czy da się połączyć przez panel. Wymaga tego reguła adresu powrotnego
     * po stronie panelu: wracamy z jawnym kluczem instalacyjnym, więc wolno
     * nam wrócić wyłącznie pod panel administracyjny łączonej domeny. Sklepy
     * z panelem na osobnej domenie parują się kluczem i tak też to opisujemy.
     */
    public function isConnectAvailable(): bool
    {
        $domain = $this->getDomain();
        if ('' === $domain) {
            return false;
        }
        $host = mb_strtolower((string) parse_url($this->backendUrl->getUrl('calmfox_watch/watch/index'), \PHP_URL_HOST));

        return '' !== $host && preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', mb_strtolower($domain));
    }

    /** Znacznik czasu w strefie panelu: właściciel sklepu nie liczy w głowie z UTC. */
    public function formatMoment(?string $value): string
    {
        if (null === $value || '' === trim($value)) {
            return '';
        }
        try {
            return $this->formatDate(new \DateTime($value), \IntlDateFormatter::MEDIUM, true);
        } catch (\Throwable) {
            return $value;
        }
    }
}
