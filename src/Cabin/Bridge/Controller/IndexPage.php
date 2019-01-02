<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Alerts\{
    FileSystem\AccessDenied,
    FileSystem\FileNotFound,
    InvalidType,
    Router\ControllerComplete,
    Security\SecurityAlert,
    Security\UserNotLoggedIn
};
use Airship\Cabin\Bridge\Model\{
    Announcements,
    Author,
    Blog,
    CustomPages
};
use Airship\Cabin\Bridge\Filter\AnnounceFilter;
use Airship\Engine\State;
use ParagonIE\Halite\Halite;

require_once __DIR__.'/init_gear.php';

/**
 * Class IndexPage
 * @package Airship\Cabin\Bridge\Controller
 */
class IndexPage extends ControllerGear
{
    /**
     * @route announce
     *
     * @throws ControllerComplete
     * @throws InvalidType
     * @throws SecurityAlert
     * @throws \TypeError
     */
    public function announce(): void
    {
        if (!$this->isLoggedIn())  {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $this->storeViewVar('showmenu', true);
        if (!$this->can('create')) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        $announce_bp = $this->model('Announcements');
        if (!($announce_bp instanceof Announcements)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Announcements::class)
            );
        }

        $post = $this->post(new AnnounceFilter());
        if ($post) {
            if ($announce_bp->createAnnouncement($post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix
                );
            }
        }
        $this->view(
            'announce',
            [
                'active_link' =>
                    'bridge-link-announce',
                'title' =>
                    \__('New Announcement')
            ]
        );
    }

    /**
     * @route /
     *
     * @throws InvalidType
     * @throws ControllerComplete
     * @throws UserNotLoggedIn
     * @throws \TypeError
     */
    public function index(): void
    {
        if ($this->isLoggedIn())  {
            $this->storeViewVar('showmenu', true);

            $author_bp = $this->model('Author');
            if (!($author_bp instanceof Author)) {
                throw new \TypeError(
                    \trk('errors.type.wrong_class', Author::class)
                );
            }
            $announce_bp = $this->model('Announcements');
            if (!($announce_bp instanceof Announcements)) {
                throw new \TypeError(
                    \trk('errors.type.wrong_class', Announcements::class)
                );
            }
            $blog_bp = $this->model('Blog');
            if (!($blog_bp instanceof Blog)) {
                throw new \TypeError(
                    \trk('errors.type.wrong_class', Blog::class)
                );
            }
            /** @var CustomPages $page_bp */
            $page_bp = $this->model('CustomPages');
            if (!($page_bp instanceof CustomPages)) {
                throw new \TypeError(
                    \trk('errors.type.wrong_class', CustomPages::class)
                );
            }

            $this->includeAjaxToken()->view('index',
                [
                    'announcements' =>
                        $announce_bp->getForUser(
                            $this->getActiveUserId()
                        ),
                    'stats' => [
                        'num_authors' =>
                            $author_bp->numAuthors(),
                        'num_comments' =>
                            $blog_bp->numComments(true),
                        'num_pages' =>
                            $page_bp->numCustomPages(true),
                        'num_posts' =>
                            $blog_bp->numPosts(true)
                    ],
                    'title' => \__('Dashboard')
                ]
            );
        } else {
            $this->storeViewVar('showmenu', false);
            $this->view('login');
        }
    }

    /**
     * @route error
     *
     * @throws ControllerComplete
     */
    public function error(): void
    {
        if (empty($_GET['error'])) {
            \Airship\redirect($this->airship_cabin_prefix);
        }
        if ($_GET['error'] === '403 Forbidden') {
            \http_response_code(403);
        }
        switch ($_GET['error']) {
            case '403 Forbidden':
                $this->view(
                    'error',
                    [
                        'error' =>
                            \__($_GET['error'])
                    ]
                );
                break;
            default:
                \Airship\redirect($this->airship_cabin_prefix);
        }
    }

    /**
     * @route help
     *
     * @throws ControllerComplete
     * @throws UserNotLoggedIn
     * @throws AccessDenied
     * @throws FileNotFound
     */
    public function helpPage(): void
    {
        if ($this->isLoggedIn())  {
            $this->storeViewVar('showmenu', true);
            //
            $cabins = $this->getCabinNamespaces();

            // Get debug information.
            $helpInfo = [
                'cabins' => [],
                'cabin_names' => \array_values($cabins),
                'gears' => [],
                'universal' => []
            ];

            /**
             * This might reveal "sensitive" information. By default, it's
             * locked out of non-administrator users. You can grant access to
             * other users/groups via the Permissions menu.
             */
            if ($this->can('read')) {
                $state = State::instance();
                if (\is_readable(ROOT . '/config/gadgets.json')) {
                    $helpInfo['universal']['gadgets'] = \Airship\loadJSON(
                        ROOT . '/config/gadgets.json'
                    );
                }
                if (\is_readable(ROOT . '/config/content_security_policy.json')) {
                    $helpInfo['universal']['content_security_policy'] = \Airship\loadJSON(
                        ROOT . '/config/content_security_policy.json'
                    );
                }
                foreach ($cabins as $cabin) {
                    $cabinData = [
                        'config' => \Airship\loadJSON(
                            ROOT . '/Cabin/' . $cabin . '/manifest.json'
                        ),
                        'content_security_policy' => [],
                        'gadgets' => [],
                        'motifs' => [],
                        'user_motifs' => \Airship\ViewFunctions\user_motif(
                            $this->getActiveUserId(),
                            $cabin
                        )
                    ];

                    $prefix  = ROOT . '/Cabin/' . $cabin . '/config/';
                    if (\is_readable($prefix . 'gadgets.json')) {
                        $cabinData['gadgets'] = \Airship\loadJSON(
                            $prefix . 'gadgets.json'
                        );
                    }
                    if (\is_readable($prefix . 'motifs.json')) {
                        $cabinData['motifs'] = \Airship\loadJSON(
                            $prefix . 'motifs.json'
                        );
                    }
                    if (\is_readable($prefix . 'content_security_policy.json')) {
                        $cabinData['content_security_policy'] = \Airship\loadJSON(
                            $prefix . 'content_security_policy.json'
                        );
                    }

                    $helpInfo['cabins'][$cabin] = $cabinData;
                }
                $helpInfo['gears'] = [];
                foreach ($state->gears as $gear => $latestGear) {
                    $helpInfo['gears'][$gear] = \Airship\get_ancestors($latestGear);
                }

                // Only grab data likely to be pertinent to common issues:
                $keys = [
                    'airship',
                    'auto-update',
                    'debug',
                    'guzzle',
                    'notary',
                    'rate-limiting',
                    'session_config',
                    'tor-only',
                    'twig_cache'
                ];
                $helpInfo['universal']['config'] = \Airship\keySlice(
                    $state->universal,
                    $keys
                );

                $helpInfo['php'] = [
                    'halite' =>
                        Halite::VERSION,
                    'libsodium' => [
                        'major' =>
                            \SODIUM_LIBRARY_MAJOR_VERSION,
                        'minor' =>
                            \SODIUM_LIBRARY_MINOR_VERSION,
                        'version' =>
                            \SODIUM_LIBRARY_VERSION
                    ],
                    'version' =>
                        \PHP_VERSION,
                    'versionid' =>
                        \PHP_VERSION_ID
                ];
            }

            $this->view(
                'help',
                [
                    'active_link' => 'bridge-link-help',
                    'airship' => \AIRSHIP_VERSION,
                    'helpInfo' => $helpInfo
                ]
            );
        } else {
            // Not a registered user? Go read the docs. No info leaks for you!
            \Airship\redirect('https://github.com/paragonie/airship/tree/master/docs');
        }
    }

    /**
     * Load the extra CSS for this motif
     *
     * @route motif_extra.css
     * @throws ControllerComplete
     */
    public function motifExtra(): void
    {
        $this->view('motif_extra', [], 'text/css; charset=UTF-8');
    }
}
