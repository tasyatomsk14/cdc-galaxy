<?php
session_start();
require __DIR__ . '/db.php';

const ROLE_TITLES = [
    'admin'   => 'Администратор',
    'manager' => 'Менеджер',
    'viewer'  => 'Наблюдатель',
];

// CSRF-токен
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function csrf_check() {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('CSRF-токен недействителен. Обновите страницу.');
    }
}

// ---- Выход ----
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ---- Вход ----
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    csrf_check();
    $login = trim($_POST['user'] ?? '');
    $pass  = $_POST['pass'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE login = ?');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['pass_hash'])) {
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_login'] = $user['login'];
        header('Location: admin.php');
        exit;
    }
    $loginError = 'Неверный логин или пароль';
}

$me = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $me = $stmt->fetch();
    if (!$me) { // пользователя удалили
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

$tab = $_GET['tab'] ?? 'bookings';

// ---- Защищённые действия ----
if ($me && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login'])) {
    csrf_check();
    $canBookings = in_array($me['role'], ['admin', 'manager'], true);
    $canUsers    = $me['role'] === 'admin';

    // Удаление заявки
    if (isset($_POST['delete_booking']) && $canBookings) {
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->execute([(int)$_POST['delete_booking']]);
        header('Location: admin.php?tab=bookings');
        exit;
    }

    // Добавление пользователя
    if (isset($_POST['add_user']) && $canUsers) {
        $login = trim($_POST['new_login'] ?? '');
        $pass  = $_POST['new_pass'] ?? '';
        $role  = $_POST['new_role'] ?? 'viewer';
        $err   = '';

        if (mb_strlen($login) < 3 || mb_strlen($login) > 50) {
            $err = 'Логин должен быть от 3 до 50 символов';
        } elseif (strlen($pass) < 4) {
            $err = 'Пароль должен быть не короче 4 символов';
        } elseif (!array_key_exists($role, ROLE_TITLES)) {
            $err = 'Неверная роль';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO users (login, pass_hash, role) VALUES (?, ?, ?)');
                $stmt->execute([$login, password_hash($pass, PASSWORD_DEFAULT), $role]);
                header('Location: admin.php?tab=users&added=1');
                exit;
            } catch (PDOException $e) {
                $err = 'Логин уже занят';
            }
        }
        $_SESSION['flash_err'] = $err;
        header('Location: admin.php?tab=users');
        exit;
    }

    // Смена роли
    if (isset($_POST['change_role']) && $canUsers) {
        $uid  = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? '';

        if ($uid === (int)$me['id']) {
            $_SESSION['flash_err'] = 'Нельзя менять роль самому себе';
        } elseif (!array_key_exists($role, ROLE_TITLES)) {
            $_SESSION['flash_err'] = 'Неверная роль';
        } else {
            // Защита: не понизить последнего админа
            if ($role !== 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                $isTargetAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $isTargetAdmin->execute([$uid]);
                if ($admins <= 1 && $isTargetAdmin->fetchColumn() === 'admin') {
                    $_SESSION['flash_err'] = 'Нельзя понизить последнего администратора';
                    header('Location: admin.php?tab=users');
                    exit;
                }
            }
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $uid]);
            header('Location: admin.php?tab=users');
            exit;
        }
        header('Location: admin.php?tab=users');
        exit;
    }

    // Удаление пользователя
    if (isset($_POST['delete_user']) && $canUsers) {
        $uid = (int)$_POST['delete_user'];
        if ($uid === (int)$me['id']) {
            $_SESSION['flash_err'] = 'Нельзя удалить самого себя';
        } else {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $targetRole = $stmt->fetchColumn();
            if ($targetRole === 'admin') {
                $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($admins <= 1) {
                    $_SESSION['flash_err'] = 'Нельзя удалить последнего администратора';
                    header('Location: admin.php?tab=users');
                    exit;
                }
            }
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$uid]);
        }
        header('Location: admin.php?tab=users');
        exit;
    }
}

// ---- Данные для отображения ----
$bookings = [];
$users    = [];
if ($me) {
    $bookings = $pdo->query('SELECT * FROM bookings ORDER BY id DESC')->fetchAll();
    if ($me['role'] === 'admin') {
        $users = $pdo->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();
    }
}

$flashErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_err']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>CDC GALAXY — Админка</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600&family=Unbounded:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php if (!$me): ?>
  <!-- ============ ЭКРАН ВХОДА ============ -->
  <div class="login">
    <div class="login-box">
      <h2>Вход в админку</h2>
      <?php if ($loginError): ?>
        <div class="err" style="display:block"><?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="text"     name="user" placeholder="Логин" required autofocus>
        <input type="password" name="pass" placeholder="Пароль" required>
        <button type="submit" name="login" value="1">Войти</button>
      </form>
      <p style="text-align:center;margin-top:15px;font-size:.85rem">
        <a href="index.html">← Вернуться на сайт</a>
      </p>
      <p style="text-align:center;margin-top:20px;font-size:.75rem;color:var(--mut)">
        По умолчанию: admin / 1234
      </p>
    </div>
  </div>

<?php else: ?>
  <!-- ============ ДАШБОРД ============ -->
  <div id="dash" style="display:flex;width:100%;height:100vh">

    <div class="side">
      <h2>CDC GALAXY</h2>
      <ul>
        <li class="<?= $tab === 'bookings' ? 'active' : '' ?>">
          <a href="admin.php?tab=bookings">📋 Заявки (<?= count($bookings) ?>)</a>
        </li>
        <?php if ($me['role'] === 'admin'): ?>
        <li class="<?= $tab === 'users' ? 'active' : '' ?>">
          <a href="admin.php?tab=users">👥 Пользователи (<?= count($users) ?>)</a>
        </li>
        <?php endif; ?>
      </ul>

      <div class="me">
        <div class="me-login">👤 <?= htmlspecialchars($me['login']) ?></div>
        <div class="me-role"><?= ROLE_TITLES[$me['role']] ?></div>
      </div>

      <a href="admin.php?logout=1" class="logout-link"><button>Выйти</button></a>
    </div>

    <div class="main">

      <?php if ($flashErr): ?>
        <div class="flash-err"><?= htmlspecialchars($flashErr) ?></div>
      <?php endif; ?>

      <?php if (isset($_GET['added'])): ?>
        <div class="flash-ok">Пользователь добавлен</div>
      <?php endif; ?>

      <?php if ($tab === 'bookings'): ?>

        <h1>Заявки</h1>

        <?php if (count($bookings) === 0): ?>
          <p class="empty">Нет заявок</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Имя</th><th>Телефон</th><th>Дата</th><th>Создано</th><th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
              <tr>
                <td><?= (int)$b['id'] ?></td>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td><?= htmlspecialchars($b['phone']) ?></td>
                <td><strong><?= htmlspecialchars($b['date']) ?></strong></td>
                <td><?= htmlspecialchars($b['created_at']) ?></td>
                <td>
                  <?php if (in_array($me['role'], ['admin','manager'], true)): ?>
                    <form method="post" style="display:inline"
                          onsubmit="return confirm('Удалить заявку?')">
                      <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                      <button type="submit" name="delete_booking"
                              value="<?= (int)$b['id'] ?>" class="del-btn">Удалить</button>
                    </form>
                  <?php else: ?>
                    <span style="color:#666">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

      <?php elseif ($tab === 'users' && $me['role'] === 'admin'): ?>

        <h1>Пользователи</h1>

        <!-- Добавление -->
        <div class="card-form">
          <h3>Добавить пользователя</h3>
          <form method="post" class="inline-form">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="text"     name="new_login" placeholder="Логин" required minlength="3" maxlength="50">
            <input type="password" name="new_pass"  placeholder="Пароль" required minlength="4">
            <select name="new_role" required>
              <?php foreach (ROLE_TITLES as $k => $v): ?>
                <option value="<?= $k ?>"><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" name="add_user" value="1" class="btn btn-org">Добавить</button>
          </form>
        </div>

        <!-- Список -->
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Логин</th><th>Роль</th><th>Создан</th><th>Изменить роль</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td>
                <?= htmlspecialchars($u['login']) ?>
                <?php if ((int)$u['id'] === (int)$me['id']): ?>
                  <span class="tag-me">(вы)</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="role-badge role-<?= $u['role'] ?>">
                  <?= ROLE_TITLES[$u['role']] ?>
                </span>
              </td>
              <td><?= htmlspecialchars($u['created_at']) ?></td>
              <td>
                <form method="post" class="role-form">
                  <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <select name="role" <?= (int)$u['id'] === (int)$me['id'] ? 'disabled' : '' ?>>
                    <?php foreach (ROLE_TITLES as $k => $v): ?>
                      <option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>>
                        <?= $v ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" name="change_role" value="1"
                          class="btn-mini"
                          <?= (int)$u['id'] === (int)$me['id'] ? 'disabled' : '' ?>>
                    Сохранить
                  </button>
                </form>
              </td>
              <td>
                <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                  <form method="post" style="display:inline"
                        onsubmit="return confirm('Удалить пользователя <?= htmlspecialchars($u['login']) ?>?')">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                    <button type="submit" name="delete_user"
                            value="<?= (int)$u['id'] ?>" class="del-btn">Удалить</button>
                  </form>
                <?php else: ?>
                  <span style="color:#666">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <div class="roles-info">
          <h4>Права ролей</h4>
          <ul>
            <li><b>Администратор</b> — полный доступ: заявки + управление пользователями.</li>
            <li><b>Менеджер</b> — просмотр и удаление заявок. Пользователей не видит.</li>
            <li><b>Наблюдатель</b> — только просмотр заявок, без удаления.</li>
          </ul>
        </div>

      <?php endif; ?>

    </div>
  </div>
<?php endif; ?>

</body>
</html>