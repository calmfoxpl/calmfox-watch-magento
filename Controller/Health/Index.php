<?php

declare(strict_types=1);

namespace Calmfox\Watch\Controller\Health;

use Calmfox\Watch\Core\ResponseSigner;
use Calmfox\Watch\Core\SecretManager;
use Calmfox\Watch\Model\PayloadProvider;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

/**
 * Sekretny adres kontrolny: GET /calmfox-watch/health?key=<32 znaki hex>.
 * Kontrakt z hubem: 200 = ok albo warn, 503 = fail, nic innego. Bez ważnego
 * klucza suche 403. To kanał danych, nie podstrona, więc odpowiedź nie trafia
 * do pamięci podręcznej ani do wyszukiwarek.
 *
 * Treść składamy TUTAJ, własnym json_encode, i oddajemy gotowe bajty jako Raw.
 * Gdyby robił to framework (JsonFactory i modyfikacje po drodze), podpis
 * liczyłby się nad czymś innym niż to, co wyjdzie na łącze.
 *
 * Nagłówek `Cache-Control: no-store` pełni tu podwójną rolę: wymaga go kontrakt,
 * a przy okazji wyklucza odpowiedź z pełnej pamięci podręcznej stron Magento
 * i z Varnisha (oba zapisują tylko odpowiedzi oznaczone jako publiczne).
 * Gdyby przed sklepem stała inna warstwa buforująca, ścieżkę trzeba z niej
 * wykluczyć: monitoring ma dostawać stan z tej chwili, nie sprzed godziny.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawFactory,
        private readonly SecretManager $secrets,
        private readonly PayloadProvider $payloads,
    ) {
    }

    public function execute(): Raw
    {
        if (!$this->secrets->accepts((string) $this->request->getParam('key', ''))) {
            return $this->respond(['error' => 'forbidden'], 403);
        }

        $this->secrets->touchLastPoll();

        $payload = $this->payloads->payload(
            PayloadProvider::SECTION_SECURITY === $this->request->getParam('section')
                ? PayloadProvider::SECTION_SECURITY
                : PayloadProvider::SECTION_HEALTH
        );

        // Echo znacznika parowania czytamy na żywo, POZA pamięcią podręczną:
        // challenge huba przychodzi zaraz po zapisaniu znacznika i trafiłby
        // na payload sprzed jego powstania.
        $pairing = $this->secrets->pairingNonce();
        if ('' !== $pairing) {
            $payload['pairing'] = $pairing;
        }

        $status = 'fail' === ($payload['status'] ?? '') ? 503 : 200;

        return $this->respond($payload, $status, (string) $this->request->getParam('nonce', ''));
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload, int $status, string $proofNonce = ''): Raw
    {
        $body = ResponseSigner::encode($payload);

        $result = $this->rawFactory->create();
        $result->setHttpResponseCode($status);
        $result->setHeader('Content-Type', 'application/json', true);
        $result->setHeader('Cache-Control', 'no-store, max-age=0', true);
        $result->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $result->setContents($body);

        if ('' !== $proofNonce) {
            $generatedAt = ResponseSigner::generatedAt();
            $result->setHeader(ResponseSigner::GENERATED_HEADER, $generatedAt, true);
            $result->setHeader(ResponseSigner::PROOF_HEADER, ResponseSigner::proof($proofNonce, $generatedAt, $body, $this->secrets->secret()), true);
        }

        return $result;
    }
}
