<?php
namespace App\Services;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AggregatorService
{
    const YAML_CONFIG_FILE = 'config/aggregators.yaml';

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly CacheInterface $cache,
        private readonly KernelInterface $kernel,
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em
    ) {
    }

    public function findAll(bool $eraseCache = false): array
    {
        $eraseCache && $this->cache->delete('articles');
        $articles = $this->cache->get('articles', function (ItemInterface $item) {
            // Refresh list after 30 minutes
            $item->expiresAfter(1800);
            return $this->articleRepository->findAll();
        });

        return $articles;
    }

    public function aggregate()
    {
        try {
            $config = Yaml::parseFile($this->kernel->getProjectDir() . DIRECTORY_SEPARATOR . self::YAML_CONFIG_FILE);
            $articles = [];

            foreach ($config['aggregators'] as $name => $aggregator) {
                // Retrieve data
                $rawData = $this->getRawData($aggregator['config']);
                // Parse data to a clean Article entity
                $aggregatedArticles = $this->convert($rawData, $aggregator['properties']);
                $articles = array_merge($articles, $aggregatedArticles);
            }

            $this->em->flush();
            return ['status' => 'success', 'message' => '', 'data' => $articles];
        } catch (\Exception $e) {
            return ['status' => 'danger', 'message' => $e->getMessage(), 'data' => null];
        }
    }

    private function getRawData(array $config): array
    {
        $response = $this->httpClient->request(
            'GET',
            $config['url']
        );

        if ($response->getStatusCode() !== 200) {
            // Handle exception
            throw new \Exception();
        }

        $content = $response->getContent();

        return match ($config['type']) {
            'xml' => $this->parseXmlWithImages($content),
            'json' => isset($config['data_root']) ?
                json_decode($content, true)[$config['data_root']] :
                json_decode($content, true)
        };
    }

    private function parseXmlWithImages(string $content): array
    {
        // With LIBXML_NOCDATA flag, we ensure to ignore CDATA from XML to get its content rather than an empty array
        $xmlSource = simplexml_load_string($content, null, LIBXML_NOCDATA);
        $xmlMedia = $xmlSource->xpath('//media:content');
        $articles = json_decode(json_encode($xmlSource->channel), true)['item'];
        foreach ($articles as $key => &$article) {
            // array_values is to prevent [0] to crash the app if values are not indexed from 0.
            $article['image'] = array_values((array) $xmlMedia[$key]->attributes()['url'])[0];
        }

        return $articles;
    }

    /**
     * Based on the data retrieved from the API, mapping the entity with the array to hydrate Article properly
     * and preparing it to be inserted into the database
     */
    private function convert(array $rawData, array $aggregatorProperties): array
    {
        $articles = [];
        foreach ($rawData as $key => $rawArticle) {
            $article = new Article();
            foreach ($aggregatorProperties as $entityProperty => $aggregatedProperty) {
                if (isset($rawArticle[$aggregatedProperty])) {
                    $method = 'set' . ucfirst($entityProperty);
                    $this->hydrateProperty($article, $method, $rawArticle, $aggregatedProperty);
                }
            }
            $article->setCreatedAt(new \DateTimeImmutable());
            $articles[] = $article;
            $this->em->persist($article);
        }

        return $articles;
    }

    /**
     * This method ensures to hydrate the Article, while converting mapped DateTimeImmutable fields.
     */
    private function hydrateProperty(Article &$article, string $method, array $rawArticle, string $aggregatedProperty)
    {
        if ($method === 'setPublishedAt') {
            $rawArticle[$aggregatedProperty] = new \DateTimeImmutable($rawArticle[$aggregatedProperty]);
        }

        $article->$method($rawArticle[$aggregatedProperty]);
    }
}
