<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Landing;

use \Airship\Alerts\CabinNotFound;
use \Airship\Cabin\Bridge\Blueprint\{
    Author,
    Blog,
    Permissions,
    UserAccounts
};
use \Airship\Engine\State;

require_once __DIR__.'/init_gear.php';

class Ajax extends LoggedInUsersOnly
{

    /**
     * @route ajax/authors_blog_posts
     */
    public function getBlogPostsForAuthor()
    {
        $auth_bp = $this->blueprint('Author');
        $blog_bp = $this->blueprint('Blog');
        if (IDE_HACKS) {
            $db = \Airship\get_database();
            $auth_bp = new Author($db);
            $blog_bp = new Blog($db);
        }

        if (empty($_POST['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = $_POST['author'] + 0;
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $_POST['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = $e + 0;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($_POST['add'])) {
            $newBlogPost = $blog_bp->getBlogPostById($_POST['add'] + 0);
            if (!empty($newBlogPost)) {
                if ($newBlogPost['author'] === $authorId) {
                    $existing[] = ($_POST['add'] + 0);
                    $response['new_item'] = $this->getLensAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newBlogPost['title'],
                                'post' => $newBlogPost['postid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $series = $blog_bp->listPostsForAuthor($authorId, $existing);
        $response['options'] = $this->getLensAsText(
            'ajax/bridge_blog_series_select_blogpost',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);

    }
    /**
     * @route ajax/get_perms_user
     */
    public function getPermsForUser()
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('You are not an administrator.')
            ]);
        }
        if (empty($_POST['username'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('You must enter a username.')
            ]);
        }
        if (empty($_POST['context'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No context provided.')
            ]);
        }
        if (empty($_POST['cabin'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No cabin provided.')
            ]);
        }
        $this->getPermissionsDataForUser(
            $_POST['context'] + 0,
            $_POST['username'],
            $_POST['cabin']
        );
    }

    /**
     * @route ajax/authors_blog_series
     */
    public function getSeriesForAuthor()
    {
        $auth_bp = $this->blueprint('Author');
        $blog_bp = $this->blueprint('Blog');
        if (IDE_HACKS) {
            $db = \Airship\get_database();
            $auth_bp = new Author($db);
            $blog_bp = new Blog($db);
        }

        if (empty($_POST['author'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('No author selected.')
            ]);
        }
        $authorId = $_POST['author'] + 0;
        if (!$this->isSuperUser()) {
            $authors = $auth_bp->getAuthorIdsForUser(
                $this->getActiveUserId()
            );
            if (!\in_array($authorId, $authors)) {
                \Airship\json_response([
                    'status' => 'ERROR',
                    'message' => \__('You do not have permission to access this author\'s posts.')
                ]);
            }
        }
        $existing = $_POST['existing'] ?? [];
        if (!\is1DArray($existing)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('One-dimensional array expected')
            ]);
        }
        foreach ($existing as $i => $e) {
            $existing[$i] = $e + 0;
        }
        $response = [
            'status' => 'OK'
        ];

        if (!empty($_POST['add'])) {
            $newSeries = $blog_bp->getSeries($_POST['add'] + 0);
            if (!empty($newSeries)) {
                if ($newSeries['author'] === $authorId) {
                    $existing[] = ($_POST['add'] + 0);
                    $response['new_item'] = $this->getLensAsText(
                        'ajax/bridge_blog_series_item',
                        [
                            'item' => [
                                'name' => $newSeries['name'],
                                'series' => $newSeries['seriesid'],
                                'data-id' => null,
                            ]
                        ]
                    );
                }
            }
        }

        $existing = $blog_bp->getAllSeriesParents($existing);

        $series = $blog_bp->getSeriesForAuthor($authorId, $existing);
        $response['options'] = $this->getLensAsText(
            'ajax/bridge_blog_series_select_series',
            [
                'items' => $series
            ]
        );

        \Airship\json_response($response);
    }

    /**
     * @route ajax/permission_test
     */
    public function permissionTest()
    {
        if (!$this->isSuperUser()) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('You are not an administrator.')
            ]);
        }
        if (empty($_POST['url'])) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('You must enter a URL.')
            ]);
        }
        try {
            $cabin = $this->getCabinNameFromURL($_POST['url']);
            $this->getPermissionDataForURL($_POST['url'], $cabin);
        } catch (CabinNotFound $ex) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('URL does not resolve to an existing Cabin.')
            ]);
        }
    }

    /**
     * @route ajax/rich_text_preview
     *
     * @return mixed
     */
    public function richTextPreview()
    {
        if (\Airship\all_keys_exist(['format', 'body'], $_POST)) {
            switch ($_POST['format']) {
                case 'HTML':
                case 'Rich Text':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\LensFunctions\purify($_POST['body'] ?? '')
                    ]);
                    break;
                case 'Markdown':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\LensFunctions\purify(
                            \Airship\LensFunctions\render_markdown($_POST['body'] ?? '', true)
                        )
                    ]);
                    break;
                case 'RST':
                    \Airship\json_response([
                        'status' => 'OK',
                        'body' => \Airship\LensFunctions\purify(
                            \Airship\LensFunctions\render_rst($_POST['body'] ?? '', true)
                        )
                    ]);
                    break;
                default:
                    \Airship\json_response([
                        'status' => 'ERROR',
                        'message' => 'Unknown format: ' . $_POST['format']
                    ]);
            }
        }
        \Airship\json_response([
            'status' => 'ERROR',
            'message' => \__('Incomplete request')
        ]);
    }

    /**
     * @param string $url
     * @param string $cabin
     */
    protected function getPermissionDataForURL(string $url, string $cabin)
    {
        $perm_bp = $this->blueprint('Permissions');
        if (IDE_HACKS) {
            $db = \Airship\get_database();
            $perm_bp = new Permissions($db);
        }

        $actions = $perm_bp->getActionNames($cabin);
        $contexts = $perm_bp->getContextsForURI($url, $cabin);
        $contextIds = $perm_bp->getContextIds($url, $cabin);
        $tree = $perm_bp->buildMultiContextGroupTree(
            $cabin,
            $contextIds,
            $actions
        );
        $list = $perm_bp->buildMultiContextUserList(
            $cabin,
            $contextIds,
            $actions
        );

        $this->lens(
            'perms/test',
            [
                'cabin' =>
                    $cabin,
                'actions' =>
                    $actions,
                'contexts' =>
                    $contexts,
                'permissions' =>
                    $tree,
                'userlist' =>
                    $list
            ]
        );
    }

    /**
     * @param int $contextId
     * @param string $username
     * @param string $cabin
     */
    protected function getPermissionsDataForUser(int $contextId, string $username, string $cabin)
    {
        $perm_bp = $this->blueprint('Permissions');
        $user_bp = $this->blueprint('UserAccounts');
        if (IDE_HACKS) {
            $db = \Airship\get_database();
            $perm_bp = new Permissions($db);
            $user_bp = new UserAccounts($db);
        }

        $user = $user_bp->getUserByUsername($username, true);
        if (empty($user)) {
            \Airship\json_response([
                'status' => 'ERROR',
                'message' => \__('There is no user with that username in the system')
            ]);
        }
        $userPerms = $perm_bp->getUserPerms($user['userid'], $contextId);
        $actions = $perm_bp->getActionNames($cabin);
        $perms = [];
        foreach ($actions as $action) {
            $perms[$action] = \in_array($action, $userPerms);
        }

        \ob_start();
        $this->lens(
            'perms/user',
            [
                'user' =>  $user,
                'actions' => $actions,
                'perms' => $perms
            ]
        );
        $body = \ob_get_clean();
        \Airship\json_response([
            'status' => 'OK',
            'message' => $body,
        ]);
    }
}
