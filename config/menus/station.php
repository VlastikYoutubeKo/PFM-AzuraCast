<?php
/**
 * Administrative dashboard configuration.
 */

use App\Enums\StationPermissions;
use App\Radio\Enums\AudioProcessingMethods;

return function (App\Event\BuildStationMenu $e) {
    $request = $e->getRequest();
    $station = $e->getStation();

    $backendConfig = $station->getBackendConfig();

    $router = $request->getRouter();

    $backendEnum = $station->getBackendTypeEnum();
    $frontendEnum = $station->getFrontendTypeEnum();

    $willDisconnectMessage = __('Restart broadcasting? This will disconnect any current listeners.');
    $willNotDisconnectMessage = __('Reload broadcasting? Current listeners will not be disconnected.');

    $reloadSupported = $frontendEnum->supportsReload();
    $reloadMessage = $reloadSupported ? $willNotDisconnectMessage : $willDisconnectMessage;

    $settings = $e->getSettings();

    $e->merge(
        [
            'start_station' => [
                'label' => __('Start Station'),
                'title' => __('Ready to start broadcasting? Click to start your station.'),
                'icon' => 'refresh',
                'url' => (string)$router->fromHere('api:stations:reload'),
                'class' => 'api-call text-success',
                'confirm' => $reloadMessage,
                'visible' => !$station->getHasStarted(),
                'permission' => StationPermissions::Broadcasting,
            ],
            'restart_station' => [
                'label' => __('Reload to Apply Changes'),
                'title' => __('Click to restart your station and apply configuration changes.'),
                'icon' => 'refresh',
                'url' => (string)$router->fromHere('api:stations:reload'),
                'class' => 'api-call text-warning btn-restart-station '
                    . (!$station->getNeedsRestart() ? 'd-none' : ''),
                'confirm' => $reloadMessage,
                'visible' => $station->getHasStarted(),
                'permission' => StationPermissions::Broadcasting,
            ],
            'profile' => [
                'label' => __('Profile'),
                'icon' => 'image',
                'url' => (string)$router->fromHere('stations:profile:index'),
            ],
            'public' => [
                'label' => __('Public Page'),
                'icon' => 'public',
                'url' => (string)$router->named('public:index', ['station_id' => $station->getShortName()]),
                'external' => true,
                'visible' => $station->getEnablePublicPage(),
            ],
            'media' => [
                'label' => __('Media'),
                'icon' => 'library_music',
                'items' => [
                    'files' => [
                        'label' => __('Music Files'),
                        'icon' => 'library_music',
                        'url' => (string)$router->fromHere('stations:files:index'),
                        'visible' => $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Media,
                    ],
                    'reports_duplicates' => [
                        'label' => __('Duplicate Songs'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:files:index') . '#special:duplicates',
                        'visible' => $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Media,
                    ],
                    'reports_unprocessable' => [
                        'label' => __('Unprocessable Files'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:files:index') . '#special:unprocessable',
                        'visible' => $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Media,
                    ],
                    'reports_unassigned' => [
                        'label' => __('Unassigned Files'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:files:index') . '#special:unassigned',
                        'visible' => $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Media,
                    ],
                    'ondemand' => [
                        'label' => __('On-Demand Media'),
                        'class' => 'text-muted',
                        'icon' => 'cloud_download',
                        'url' => (string)$router->named('public:ondemand', ['station_id' => $station->getShortName()]),
                        'external' => true,
                        'visible' => $station->getEnableOnDemand(),
                    ],
                    'sftp_users' => [
                        'label' => __('SFTP Users'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:sftp_users:index'),
                        'visible' => App\Service\SftpGo::isSupportedForStation($station),
                        'permission' => StationPermissions::Media,
                    ],
                    'bulk_media' => [
                        'label' => __('Bulk Media Import/Export'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:bulk-media'),
                        'visible' => $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Media,
                    ],
                ],
            ],

            'playlists' => [
                'label' => __('Playlists'),
                'icon' => 'queue_music',
                'url' => (string)$router->fromHere('stations:playlists:index'),
                'visible' => $backendEnum->isEnabled(),
                'permission' => StationPermissions::Media,
            ],

            'podcasts' => [
                'label' => __('Podcasts'),
                'icon' => 'cast',
                'url' => (string)$router->fromHere('stations:podcasts:index'),
                'permission' => StationPermissions::Podcasts,
            ],

            'live_streaming' => [
                'label' => __('Live Streaming'),
                'icon' => 'mic',
                'items' => [
                    'streamers' => [
                        'label' => __('Streamer/DJ Accounts'),
                        'icon' => 'mic',
                        'url' => (string)$router->fromHere('stations:streamers:index'),
                        'visible' => $backendEnum->isEnabled() && $station->getEnableStreamers(),
                        'permission' => StationPermissions::Streamers,
                    ],

                    'web_dj' => [
                        'label' => __('Web DJ'),
                        'icon' => 'surround_sound',
                        'url' => (string)$router->named(
                            'public:dj',
                            ['station_id' => $station->getShortName()],
                            [],
                            true
                        )
                            ->withScheme('https'),
                        'visible' => $station->getEnablePublicPage() && $station->getEnableStreamers(),
                        'external' => true,
                    ],
                ],
            ],

            'webhooks' => [
                'label' => __('Web Hooks'),
                'icon' => 'code',
                'url' => (string)$router->fromHere('stations:webhooks:index'),
                'permission' => StationPermissions::WebHooks,
            ],

            'reports' => [
                'label' => __('Reports'),
                'icon' => 'assignment',
                'permission' => StationPermissions::Reports,
                'items' => [
                    'reports_overview' => [
                        'label' => __('Station Statistics'),
                        'url' => (string)$router->fromHere('stations:reports:overview'),
                    ],
                    'reports_listeners' => [
                        'label' => __('Listeners'),
                        'url' => (string)$router->fromHere('stations:reports:listeners'),
                    ],
                    'reports_requests' => [
                        'label' => __('Song Requests'),
                        'url' => (string)$router->fromHere('stations:reports:requests'),
                        'visible' => $station->getEnableRequests(),
                    ],
                    'reports_timeline' => [
                        'label' => __('Song Playback Timeline'),
                        'url' => (string)$router->fromHere('stations:reports:timeline'),
                    ],
                    'reports_soundexchange' => [
                        'label' => __('SoundExchange Royalties'),
                        'url' => (string)$router->fromHere('stations:reports:soundexchange'),
                    ],
                ],
            ],

            'broadcasting' => [
                'label' => __('Broadcasting'),
                'icon' => 'wifi_tethering',
                'items' => [
                    'mounts' => [
                        'label' => __('Mount Points'),
                        'icon' => 'wifi_tethering',
                        'url' => (string)$router->fromHere('stations:mounts:index'),
                        'visible' => $frontendEnum->supportsMounts(),
                        'permission' => StationPermissions::MountPoints,
                    ],
                    'hls_streams' => [
                        'label' => __('HLS Streams'),
                        'url' => (string)$router->fromHere('stations:hls_streams:index'),
                        'visible' => $backendEnum->isEnabled() && $station->getEnableHls(),
                        'permission' => StationPermissions::MountPoints,
                    ],
                    'remotes' => [
                        'label' => __('Remote Relays'),
                        'icon' => 'router',
                        'url' => (string)$router->fromHere('stations:remotes:index'),
                        'permission' => StationPermissions::RemoteRelays,
                    ],
                    'fallback' => [
                        'label' => __('Custom Fallback File'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:fallback'),
                        'permission' => StationPermissions::Broadcasting,
                    ],
                    'ls_config' => [
                        'label' => __('Edit Liquidsoap Configuration'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:util:ls_config'),
                        'visible' => $settings->getEnableAdvancedFeatures() && $backendEnum->isEnabled(),
                        'permission' => StationPermissions::Broadcasting,
                    ],
                    'stations:stereo_tool_config' => [
                        'label' => __('Upload Stereo Tool Configuration'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:stereo_tool_config'),
                        'visible' => $settings->getEnableAdvancedFeatures()
                            && App\Radio\Enums\BackendAdapters::Liquidsoap === $backendEnum
                            && AudioProcessingMethods::StereoTool === $backendConfig->getAudioProcessingMethodEnum(),
                        'permission' => StationPermissions::Broadcasting,
                    ],
                    'queue' => [
                        'label' => __('Upcoming Song Queue'),
                        'class' => 'text-muted',
                        'url' => (string)$router->fromHere('stations:queue:index'),
                        'permission' => StationPermissions::Broadcasting,
                    ],
                    'reload' => [
                        'label' => __('Reload Configuration'),
                        'class' => 'text-muted api-call',
                        'url' => (string)$router->fromHere('api:stations:reload'),
                        'confirm' => $willNotDisconnectMessage,
                        'permission' => StationPermissions::Broadcasting,
                        'visible' => $reloadSupported,
                    ],
                    'restart' => [
                        'label' => __('Restart Broadcasting'),
                        'class' => 'text-muted api-call',
                        'url' => (string)$router->fromHere('api:stations:restart'),
                        'confirm' => $willDisconnectMessage,
                        'permission' => StationPermissions::Broadcasting,
                    ],
                ],
            ],

            'help' => [
                'label' => __('Help'),
                'icon' => 'support',
                'url' => (string)$router->fromHere('stations:help'),
                'permission' => StationPermissions::Logs,
            ],
        ]
    );
};
