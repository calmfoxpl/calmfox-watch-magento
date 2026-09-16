<?php

declare(strict_types=1);

namespace Calmfox\Watch\Block\Adminhtml;

use Calmfox\Watch\Core\PlanState;
use Calmfox\Watch\Core\StateStore;
use Calmfox\Watch\Core\StatusSummary;
use Calmfox\Watch\Model\HubClient;
use Calmfox\Watch\Model\PayloadProvider;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface as BackendUrl;

/**
 * Kafelek kondycji na pulpicie panelu. Wchodzi tam, gdzie człowiek ląduje po
 * zalogowaniu, i mówi jedno z dwóch: „nic nie wymaga uwagi" albo „to jest
 * zepsute". Pełna tabela zostaje na ekranie modułu, tutaj mieszczą się trzy
 * najpilniejsze sprawy i liczba pozostałych.
 *
 * Uprawnienie sprawdzamy sami, mimo że pulpit i tak wymaga zalogowania:
 * dostęp do pulpitu ma w Magento praktycznie każda rola, a stan wnętrza
 * sklepu jest osobnym zasobem (Calmfox_Watch::watch).
 */
class DashboardWidget extends Template
{
    /** Tyle pozycji mieści się w kafelku bez robienia z niego drugiej tabeli. */
    private const PROBLEM_LIMIT = 3;

    public function __construct(
        Context $context,
        private readonly StateStore $state,
        private readonly PayloadProvider $payloads,
        private readonly HubClient $hub,
        private readonly BackendUrl $backendUrl,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        return $this->_authorization->isAllowed('Calmfox_Watch::watch');
    }

    public function isConnected(): bool
    {
        return (bool) $this->state->get('connected', false);
    }

    /**
     * Skrót obu sekcji. Payload jest ten sam, który jedzie do huba, i ta sama
     * pamięć podręczna (60 s / 10 min), więc otwarcie pulpitu nie każe sklepowi
     * liczyć wszystkiego od nowa.
     *
     * @return array{status: string, counts: array<string, int>, problems: array<int, array<string, mixed>>, total: int}
     */
    public function getSummary(): array
    {
        if (!$this->isConnected()) {
            return StatusSummary::of([], [], self::PROBLEM_LIMIT);
        }

        return StatusSummary::of(
            $this->payloads->payload(PayloadProvider::SECTION_HEALTH),
            $this->payloads->payload(PayloadProvider::SECTION_SECURITY),
            self::PROBLEM_LIMIT
        );
    }

    /**
     * Parametry monitoringu, czyli to, co w tym sklepie w ogóle pilnujemy. Kafelek
     * pokazuje je nawet wtedy, gdy wszystko działa: bez tej listy „nic nie wymaga
     * uwagi" nie mówi, CZEGO właściwie nic nie wymaga.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getChecks(): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        $health = $this->payloads->payload(PayloadProvider::SECTION_HEALTH);

        return \is_array($health['checks'] ?? null) ? $health['checks'] : [];
    }

    public function isFreePlan(): bool
    {
        return PlanState::isFree($this->state->get('plan', ''));
    }

    /** Ekran wyboru pakietu w panelu, w kontekście tego sklepu. */
    public function getPlanUrl(): string
    {
        return $this->hub->panelLink('/app/plan');
    }

    public function getLastPollAt(): ?int
    {
        $last = (int) $this->state->get('lastPollAt', 0);

        return $last > 0 ? $last : null;
    }

    public function getScreenUrl(): string
    {
        return $this->backendUrl->getUrl('calmfox_watch/watch/index');
    }
}
