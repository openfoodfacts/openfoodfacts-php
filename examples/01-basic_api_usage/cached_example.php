

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

include '../../vendor/autoload.php';
$logger     = new \Psr\Log\NullLogger;
$httpClient = new \GuzzleHttp\Client();
// the PSR-6 cache object that you want to use (you might also use a PSR-16 Interface Object directly)
$psr6Cache  = new FilesystemAdapter();
$psr16Cache = new Psr16Cache($psr6Cache);
$api        = new \OpenFoodFacts\Api('food', 'world', $logger, $httpClient, $psr16Cache);
$product    = $api->getProduct(rand(1, 50));
