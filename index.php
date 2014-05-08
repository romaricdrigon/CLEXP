<?php 
    
require_once __DIR__ . '/../../claroline/inc/claro_init_global.inc.php';
require_once __DIR__ . '/../../claroline/inc/lib/user.lib.php';
require_once __DIR__ . '/exporter.lib.php';
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$course = 'GE01';

//temporary file directory wich is going to be zipped
@mkdir(__DIR__ . "/{$course}");

$data = array();
$data['properties'] = export_properties($course);
$data['members']['users'] = export_users($course);
$data['roles'] = export_roles($course);
$data['tools'] = export_tools($course);

//add UTF-8 encoding
file_put_contents(__DIR__ . "/{$course}/manifest.yml", utf8_encode(Yaml::dump($data, 10)));
$zipArchive = new \ZipArchive();
unlink(__DIR__ . "/{$course}.zip");
$zipArchive->open(__DIR__ . "/{$course}.zip", \ZipArchive::CREATE);

$dir = __DIR__ . "/{$course}";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $el) {
    if ($el->isFile()) {
        $zipArchive->addFile($el->getPathName(), relativePath($dir, $el->getPathName())); 
    }
}

$zipArchive->close();

foreach ($iterator as $fileinfo) {
    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
    $todo($fileinfo->getRealPath());
}

rmdir($dir);

function export_properties($course)
{
    return array(
        'name' => 'nom',
        'code' => $course,
        'visible' => true,
        'self_registration' => true,
        'self_unregistration' => true,
        'owner' => null
    );
}

function export_users($course)
{
    $users = claro_export_user_list($course);
    $export = [];

    foreach ($users as $user) {
        $email = $user['email'] == null ?
            $user['username'] . '@claco.com':
            $user['email'];
        
        $el = array('user' => array(
            'first_name' => $user['prenom'],
            'last_name' => $user['nom'],
            'username' => $user['username'],
            'code' => $user['username'],
            'mail' => $email
        ));
        
        $roles = array();
        $roles[] = array('name' => 'ROLE_WS_COLLABORATOR');
        
        if ($user['isCourseManager']) {
            $roles[] = array('name' => 'ROLE_WS_MANAGER');
        }
        
        if ($user['isPlatformAdmin']) {
            $roles[] = array('name' => 'ROLE_ADMIN');
        }
        
        if ($user['isCourseCreator']) {
            $roles[] = array('name' => 'ROLE_WS_CREATOR');
        }
        
        $el['user']['roles'] = $roles;
        
        $export[] = $el;
    }
    
    
    return $export;
}

function export_roles($course)
{
    $roles = array(
        array(
            'role' => array(
                'name' => 'ROLE_WS_COLLABORATOR', 
                'translation' => 'collaborator', 
                'is_base_role' => false
            )
        )
    );
    
    return $roles;
}

function export_tools($course)
{
    $resmData = export_resource_manager($course);
    
    $tools = array();
    
    $home = array(
        'tool' => array(
            'type' => 'home', 
            'translation' => 'accueil', 
            'roles' => array(array('name' => 'ROLE_WS_COLLABORATOR'))
        )
    );
    
    $resm = array(
        'tool' => array(
            'type' => 'resource_manager',
            'translation' => 'ressources', 
            'roles' => array(array('name' => 'ROLE_WS_COLLABORATOR')),
            'data' => $resmData
        )
    );
    
    
    $tools[] = $home;
    $tools[] = $resm;
    
    return $tools;
}

function export_resource_manager($course)
{
    $rootDir = __DIR__ . "/../../courses/{$course}/document";
    $uid = 1;
    $directories = array();
    $items = array();
    export_directory($rootDir, $directories, $items, $uid, 0, $course);
    $items = export_forums($course, $items);
    
    $data = array(
        'root' => array('uid' => 0, 'roles' => array(get_resource_base_role())),
        'directories' => $directories,
        'items' => $items
    );
    
    return $data;
}

function export_directory(
    $dir, 
    &$directories, 
    &$items, 
    &$uid, 
    $parent, 
    $course
)
{
    $iterator = new \DirectoryIterator($dir);
    $ds = DIRECTORY_SEPARATOR;
    $parent = $uid - 1;
    
    foreach ($iterator as $item) {
                
        if ($item->isFile()) {
            $finfo = finfo_open();
            $fileinfo = finfo_file($finfo, $item->getPathName(), FILEINFO_MIME);
            $fi = explode(';', $fileinfo);
            $extension = pathinfo($item->getBaseName(), PATHINFO_EXTENSION);
            $uniqid = uniqid() . '.' . $extension;
            copy($item->getPathName(), __DIR__ .  "/{$course}/{$uniqid}");
            
            $items[] = array(
                    'item' => array(
                    'name' => utf8_encode($item->getBaseName()),
                    'creator' => null,
                    'parent' => $parent,
                    'type' => 'file',
                    'roles' => array(get_resource_base_role()),
                    'data' => array(
                        array(
                            'file' => array(
                                'path' => $uniqid,
                                'mime_type' => $fi[0]
                            )
                        )
                    )
                )
            );
        }
        
        if ($item->isDir() && !$item->isDot()) {
            $directories[] = array(
                'directory' => array(
                    'name' => utf8_encode($item->getBaseName()),
                    'creator' => null,
                    'parent' => $parent,
                    'uid' => $uid,
                    'roles' => array(get_resource_base_role())
                )
            );
                
            $uid++;
            
            export_directory(
                $dir . $ds . $item->getBaseName(), 
                $directories, 
                $items, 
                $uid, 
                $parent,
                $course
            );
        }
    }
    
    return $directories;
}

function export_forums($course, $items)
{
    $categories = claro_export_category_list($course);
    @mkdir(__DIR__ . "/{$course}/forum");
    
    //$item list 
    foreach ($categories as $category) {
        $item = array(
            'name' => $category['cat_title'],
            'creator' => null,
            'parent' => 0,
            'type' => 'claroline_forum',
            'import' => array(array('path' => 'forum_' . $category['cat_id'] . '.yml'))
        );
        
        $items[] = array('item' => $item);
        $forums = claro_export_forum_list($course, $category['cat_id']);
        $categories = array();
        
        foreach ($forums as $forum) {
            $subjects = array();
            $topics = claro_export_topic_list($course, $forum['forum_id']);
            
            foreach ($topics as $topic) {
                $messages = array();
                $posts = claro_export_forum_post($course, $topic['topic_id']);
                @mkdir(__DIR__ . "/{$course}/forum/{$topic['topic_id']}");
                
                foreach ($posts as $post) {
                    $uniqid = uniqid() . '.txt';
                    file_put_contents(
                        __DIR__ . "/{$course}/forum/{$topic['topic_id']}/{$uniqid}", 
                        utf8_encode($post['post_text'])
                    );

                    $creator = user_get_properties($post['poster_id']);
                    $messages[] = array(
                        'message' => array(
                            'path' => "forum/{$topic['topic_id']}/{$uniqid}",
                            'creator' => $creator['username'],
                            'creation_date' => $post['post_time']
                        )
                    );
                }
                
                $subjects[] = array(
                    'subject' => array(
                        'name' => $topic['topic_title'],
                        'creator' => null,
                        'messages' => $messages
                    )
                );
            }
            
            $categories[] = array(
                'category' => array(
                    'name' => $forum['forum_name'],
                    'subjects' => $subjects
                )
            );
        }
        
        $data['data'] = $categories;
        
        //var_dump(utf8_encode(Yaml::dump($data, 10)));
        
        file_put_contents(
            __DIR__ . "/{$course}/forum_{$category['cat_id']}.yml", 
            utf8_encode(Yaml::dump($data, 10))
        );
    }
    
    return $items;
}

function get_resource_base_role()
{
    return array(
        'role' => array(
            'name' => 'ROLE_WS_COLLABORATOR',
            'rights' => array(
                'open' => true,
                'export' => true
            )
        )
    );
}

/*
 * http://www.php.net/manual/en/function.realpath.php
 */
function relativePath($from, $to, $ps = DIRECTORY_SEPARATOR)
{
    $arFrom = explode($ps, rtrim($from, $ps));
    $arTo = explode($ps, rtrim($to, $ps));
    
    while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0])) {
        array_shift($arFrom);
        array_shift($arTo);
    }
    
    return str_pad("", count($arFrom) * 3, '..'.$ps).implode($ps, $arTo);
}
