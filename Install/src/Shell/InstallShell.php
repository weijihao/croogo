<?php

namespace Croogo\Install\Shell;

use App\Controller\Component\AuthComponent;
use Cake\Controller\ComponentRegistry;
use Cake\Console\Shell;
use Cake\ORM\TableRegistry;
use Croogo\Install\InstallManager;
use Croogo\Install\Model\Table\InstallTable;

/**
 * Install Shell
 *
 * @category Shell
 * @package  Croogo.Install.Console.Command
 * @version  1.0
 * @author   Fahad Ibnay Heylaal <contact@fahad19.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.croogo.org
 */
class InstallShell extends Shell
{

/**
 * Display help/options
 */
    public function getOptionParser()
    {
        $drivers = ['Mysql', 'Postgres', 'Sqlite', 'Sqlserver'];
        $parser = parent::getOptionParser();
        $parser->description(__d('croogo', 'Install Utilities'))
            ->addSubcommand('main', [
                'help' => 'Generate database.php and create admin user.',
                'parser' => [
                    'description' => 'Generate database.php and create admin user.',
                ]
            ])
            ->addSubcommand('setup_acos', [
                'help' => 'Setup default ACOs for roles',
                'parser' => [
                    'description' => 'Generate default role settings during release',
                ]
            ])
            ->addOption('datasource', [
                'help' => 'Database Driver',
                'short' => 'd',
                'required' => true,
                'options' => $drivers,
            ])
            ->addOption('host', [
                'help' => 'Database Host',
                'short' => 'h',
                'required' => true,
            ])
            ->addOption('username', [
                'help' => 'Database User',
                'short' => 'u',
                'required' => true,
            ])
            ->addOption('password', [
                'help' => 'Database Password',
                'short' => 'p',
                'required' => true,
            ])
            ->addOption('database-name', [
                'help' => 'Database Name',
                'short' => 'n',
                'required' => true,
            ])
            ->addOption('port', [
                'help' => 'Database Port',
                'short' => 't',
                'required' => true,
            ])
            //->addOption('prefix', [
                //'help' => 'Table Prefix',
                //'short' => 'x',
                //'required' => true,
            //])
            ->addArgument('admin-user', [
                'help' => 'Admin username',
            ])
            ->addArgument('admin-password', [
                'help' => 'Admin password',
            ]);
        return $parser;
    }

/**
 * Convenient wrapper for Shell::in() that gets the default from CLI options
 */
    protected function _in($prompt, $options = null, $default = null, $argument = null)
    {
        if (isset($this->params[$argument])) {
            return $this->params[$argument];
        }
        return $this->in($prompt, $options, $default);
    }

/**
 * Convenient wrapper for Shell::in() that gets the default from CLI argument
 */
    protected function _args($prompt, $options = null, $default = null, $index = null)
    {
        if (!empty($this->args[$index])) {
            return $this->args[$index];
        }
        return $this->in($prompt, $options, $default);
    }

    public function main()
    {
        $this->out();
        $this->out('Database settings:');
        $install['Install']['datasource'] = $this->_in(__d('croogo', 'DataSource'), [
            'Mysql',
            'Sqlite',
            'Postgres',
            'Sqlserver'
        ], 'Mysql', 'datasource');
        $install['Install']['driver'] = 'Cake\Database\Driver\\' . $install['Install']['datasource'];
        $install['Install']['host'] = $this->_in(__d('croogo', 'Host'), null, 'localhost', 'host');
        $install['Install']['username'] = $this->_in(__d('croogo', 'Login'), null, 'root', 'username');
        $install['Install']['password'] = $this->_in(__d('croogo', 'Password'), null, '', 'password');
        $install['Install']['database'] = $this->_in(__d('croogo', 'Database'), null, 'croogo', 'database-name');
        //$install['Install']['prefix'] = $this->_in(__d('croogo', 'Prefix'), null, '', 'prefix');
        $install['Install']['port'] = $this->_in(__d('croogo', 'Port'), null, null, 'port');

        $InstallManager = new InstallManager();
        $InstallManager->createDatabaseFile($install);

        $this->out('Setting up database objects. Please wait...');
        $Install = TableRegistry::get('Croogo/Install.Install');
        try {
            $result = $Install->setupDatabase();
            if ($result !== true) {
                $this->err($result);
                return $this->_stop();
            }
        } catch (Exception $e) {
            $this->err($e->getMessage());
            $this->err('Please verify you have the correct credentials');
            return $this->_stop();
        }

        $this->out();
        if (empty($this->args)) {
            $this->out('Create Admin user:');
        }

        do {
            $username = $this->_args(__d('croogo', 'Username'), null, null, 0);
            if (empty($username)) {
                $this->err('Username must not be empty');
            }
        } while (empty($username));

        do {
            $password = $this->_args(__d('croogo', 'Password'), null, null, 1);
            if (empty($this->args)) {
                $verify = $this->_in(__d('croogo', 'Verify Password'), null, null, 1);
                $passwordsMatched = $password == $verify;

                if (!$passwordsMatched) {
                    $this->err('Passwords do not match');
                }
            } else {
                $passwordsMatched = true;
            }
            if (empty($password)) {
                $this->err('Password must not be empty');
            }
        } while (empty($password) || !$passwordsMatched);

        $user = ['username' => $username, 'password' => $password];

        $Install->addAdminUser($user);
        $InstallManager->installCompleted();

        $this->out();
        $this->success('Congratulations, Croogo has been installed successfully.');
    }

    public function setupAcos()
    {
        $Roles = TableRegistry::get('Croogo/Users.Roles');
        $Roles->addBehavior('Croogo/Core.Aliasable');

        $Permission = TableRegistry::get('Croogo/Acl.Permissions');
        $public = 'Role-public';
        $registered = 'Role-registered';

        $setup = [
//            'controllers/Croogo\Comments/Comments/index' => [$public],
//            'controllers/Croogo\Comments/Comments/add' => [$public],
//            'controllers/Croogo\Comments/Comments/delete' => [$registered],
            'controllers/Croogo\Contacts/Contacts/view' => [$public],
            'controllers/Croogo\Nodes/Nodes/index' => [$public],
            'controllers/Croogo\Nodes/Nodes/term' => [$public],
            'controllers/Croogo\Nodes/Nodes/promoted' => [$public],
            'controllers/Croogo\Nodes/Nodes/search' => [$public],
            'controllers/Croogo\Nodes/Nodes/view' => [$public],
            'controllers/Croogo\Users/Users/index' => [$registered],
            'controllers/Croogo\Users/Users/add' => [$public],
            'controllers/Croogo\Users/Users/activate' => [$public],
            'controllers/Croogo\Users/Users/edit' => [$registered],
            'controllers/Croogo\Users/Users/forgot' => [$public],
            'controllers/Croogo\Users/Users/reset' => [$public],
            'controllers/Croogo\Users/Users/login' => [$public],
            'controllers/Croogo\Users/Users/logout' => [$registered],
            'controllers/Croogo\Users/Admin/Users/logout' => [$registered],
            'controllers/Croogo\Users/Users/view' => [$registered],
        ];

        foreach ($setup as $aco => $roles) {
            foreach ($roles as $aro) {
                try {
                    $result = $Permission->allow($aro, $aco);
                    if ($result) {
                        $this->success(__d('croogo', 'Permission %s granted to %s', $aco, $aro));
                    }
                }
                catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
            }
        }
    }
}