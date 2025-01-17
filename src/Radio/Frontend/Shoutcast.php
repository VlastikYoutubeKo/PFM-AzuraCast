<?php

declare(strict_types=1);

namespace App\Radio\Frontend;

use App\Entity;
use App\Service\Acme;
use Exception;
use NowPlaying\Result\Result;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

class Shoutcast extends AbstractFrontend
{
    /**
     * @inheritDoc
     */
    public function getBinary(): ?string
    {
        $new_path = '/var/azuracast/servers/shoutcast2/sc_serv';
        return file_exists($new_path)
            ? $new_path
            : null;
    }

    public function getVersion(): ?string
    {
        $binary = $this->getBinary();
        if (!$binary) {
            return null;
        }

        $process = new Process([$binary, '--version']);
        $process->setWorkingDirectory(dirname($binary));
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return preg_match('/^SHOUTcast .* v(\S+) .*$/i', $process->getOutput(), $matches)
            ? $matches[1]
            : null;
    }

    public function getNowPlaying(Entity\Station $station, bool $includeClients = true): Result
    {
        $feConfig = $station->getFrontendConfig();
        $radioPort = $feConfig->getPort();

        $baseUrl = $this->environment->getLocalUri()
            ->withPort($radioPort);

        $npAdapter = $this->adapterFactory->getShoutcast2Adapter($baseUrl);
        $npAdapter->setAdminPassword($feConfig->getAdminPassword());

        $defaultResult = Result::blank();
        $otherResults = [];

        $sid = 0;
        foreach ($station->getMounts() as $mount) {
            $sid++;

            try {
                $result = $npAdapter->getNowPlaying((string)$sid, $includeClients);

                if (!empty($result->clients)) {
                    foreach ($result->clients as $client) {
                        $client->mount = 'local_' . $mount->getId();
                    }
                }
            } catch (Exception $e) {
                $this->logger->error(sprintf('NowPlaying adapter error: %s', $e->getMessage()));

                $result = Result::blank();
            }

            $mount->setListenersTotal($result->listeners->total);
            $mount->setListenersUnique($result->listeners->unique ?? 0);
            $this->em->persist($mount);

            if ($mount->getIsDefault()) {
                $defaultResult = $result;
            } else {
                $otherResults[] = $result;
            }
        }

        $this->em->flush();

        foreach ($otherResults as $otherResult) {
            $defaultResult = $defaultResult->merge($otherResult);
        }

        return $defaultResult;
    }

    public function getConfigurationPath(Entity\Station $station): ?string
    {
        return $station->getRadioConfigDir() . '/sc_serv.conf';
    }

    public function getCurrentConfiguration(Entity\Station $station): ?string
    {
        $configPath = $station->getRadioConfigDir();
        $frontendConfig = $station->getFrontendConfig();

        [$certPath, $certKey] = Acme::getCertificatePaths();

        $config = [
            'password' => $frontendConfig->getSourcePassword(),
            'adminpassword' => $frontendConfig->getAdminPassword(),
            'logfile' => $configPath . '/sc_serv.log',
            'w3clog' => $configPath . '/sc_w3c.log',
            'banfile' => $this->writeIpBansFile($station),
            'agentfile' => $this->writeUserAgentBansFile($station, 'sc_serv.agent'),
            'ripfile' => $configPath . '/sc_serv.rip',
            'maxuser' => $frontendConfig->getMaxListeners() ?? 250,
            'portbase' => $frontendConfig->getPort(),
            'requirestreamconfigs' => 1,
            'savebanlistonexit' => '0',
            'saveagentlistonexit' => '0',
            'licenceid' => $frontendConfig->getScLicenseId(),
            'userid' => $frontendConfig->getScUserId(),
            'sslCertificateFile' => $certPath,
            'sslCertificateKeyFile' => $certKey,
        ];

        $customConfig = trim($frontendConfig->getCustomConfiguration() ?? '');
        if (!empty($customConfig)) {
            $custom_conf = $this->processCustomConfig($customConfig);

            if (false !== $custom_conf) {
                $config = array_merge($config, $custom_conf);
            }
        }

        $i = 0;
        foreach ($station->getMounts() as $mount_row) {
            /** @var Entity\StationMount $mount_row */
            $i++;
            $config['streamid_' . $i] = $i;
            $config['streampath_' . $i] = $mount_row->getName();

            if (!empty($mount_row->getIntroPath())) {
                $introPath = $mount_row->getIntroPath();
                $config['streamintrofile_' . $i] = $station->getRadioConfigDir() . '/' . $introPath;
            }

            if ($mount_row->getRelayUrl()) {
                $config['streamrelayurl_' . $i] = $mount_row->getRelayUrl();
            }

            if ($mount_row->getAuthhash()) {
                $config['streamauthhash_' . $i] = $mount_row->getAuthhash();
            }

            if ($mount_row->getMaxListenerDuration()) {
                $config['streamlistenertime_' . $i] = $mount_row->getMaxListenerDuration();
            }
        }

        $configFileOutput = '';
        foreach ($config as $config_key => $config_value) {
            $configFileOutput .= $config_key . '=' . str_replace("\n", '', (string)$config_value) . "\n";
        }

        return $configFileOutput;
    }

    public function getCommand(Entity\Station $station): ?string
    {
        if ($binary = $this->getBinary()) {
            return $binary . ' ' . $this->getConfigurationPath($station);
        }
        return null;
    }

    public function getAdminUrl(Entity\Station $station, UriInterface $base_url = null): UriInterface
    {
        $public_url = $this->getPublicUrl($station, $base_url);
        return $public_url
            ->withPath($public_url->getPath() . '/admin.cgi');
    }

    protected function writeIpBansFile(
        Entity\Station $station,
        string $fileName = 'sc_serv.ban',
        string $ipsSeparator = ";255;\n"
    ): string {
        $ips = $this->getBannedIps($station);

        $configDir = $station->getRadioConfigDir();
        $bansFile = $configDir . '/' . $fileName;

        $bannedIpsString = implode($ipsSeparator, $ips);
        if (!empty($bannedIpsString)) {
            $bannedIpsString .= $ipsSeparator;
        }

        file_put_contents($bansFile, $bannedIpsString);

        return $bansFile;
    }
}
