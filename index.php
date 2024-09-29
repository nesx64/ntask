<?php
$pdo = new PDO('mysql:host=localhost;dbname=ntask', 'root', '2343');

$login = false;
$register = false;
$admin = false;
$allow_pannel = false;
$connected = false;

$user_registration_status = "";
$user_login_status = "";

if ($_SERVER['REQUEST_URI'] == '/login') {
    $login = true;
} else if ($_SERVER['REQUEST_URI'] == '/register') {
    $register = true;
}

if (isset($_COOKIE['userID'])) {
    $userQuery = ("SELECT * FROM user WHERE id = " . $_COOKIE['userID']);
    $stmt = $pdo->query($userQuery);
    $user = $stmt->fetch();
    if ($user["id"] == $_COOKIE['userID']) {
        $connected = true;
        session_start();
        $current_user = $user['username'];
        global $current_user;
        if ($user['admin'] == 1) {
            $admin = true;
        }
    } else {
        setcookie('userID', '', time() - 3600);
    }
}

if ($_SERVER['REQUEST_URI'] == '/panel' && $admin) {
    $allow_panel = true;
} else if ($_SERVER['REQUEST_URI'] == '/panel' && !$admin) {
    header('Location: /');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $login) {
    echo 'Hello login';
    $username = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
    $password = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '';

    if (!empty($username) && !empty($password)) {
        $loginQuery = ("SELECT * FROM user WHERE username = '$username' AND password = '$password'");
        $stmt = $pdo->query($loginQuery);
        $user = $stmt->fetch();
        if (!$user) {
            $user_login_status = "Invalid username or password";
        } else {
            $connected = true;
            $user_login_status = "User logged in successfully";
            setcookie('userID', $user['id'], time() + 3600);
            header('Location: /');
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'POST' && $register) {
    echo 'Hello register';
    $username = isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '';
    $password = isset($_POST['password']) ? htmlspecialchars($_POST['password']) : '';
    $email = isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) : '';

    if (!empty($username) && !empty($password) && !empty($email)) {
        $registerQuery = "INSERT INTO user (username, password, email) VALUES ('$username', '$password', '$email');";
        try {
            $pdo->query($registerQuery);
            $user_registration_status = "User registered successfully";
        } catch (PDOException $e) {
            var_dump($e);
            $user_registration_status = "User registration failed, username already exists.";
        }
    }
} else if ($_SERVER['REQUEST_URI'] == '/logout') {
    echo 'Hello logout';
    setcookie('userID', '', time() - 3600);
    header('Location: /');
    setcookie('logged_out_message', 'You have been logged out.', time() + 10);
} else if ($_SERVER['REQUEST_URI'] == '/create-task' && $connected && $_SERVER['REQUEST_METHOD'] == 'POST') {
    echo 'Hello create-task';
    $taskName = isset($_POST['taskName']) ? htmlspecialchars($_POST['taskName']) : '';
    $taskDescription = isset($_POST['taskDescription']) ? htmlspecialchars($_POST['taskDescription']) : null;
    $taskDueDate = isset($_POST['taskDueDate']) ? htmlspecialchars($_POST['taskDueDate']) : null;
    $taskImage = isset($_POST['taskImage']) ? htmlspecialchars($_POST['taskImage']) : null;

    if (!empty($taskName)) {
        $taskImagePath = null;
        if (isset($_FILES['taskImage'])) {
            $taskImagePath = $_FILES['taskImage']['tmp_name'];
        }

        $taskImageBlob = file_get_contents($taskImagePath);
        $taskDueDate = !empty($taskDueDate) ? strtotime($taskDueDate) : null;
        $createTaskQuery = "INSERT INTO task (name, description, due_date, image, user_id, completed) VALUES (:name, :description, FROM_UNIXTIME(:due_date), :image, :user_id, 0)";
        $stmt = $pdo->prepare($createTaskQuery);
        $stmt->bindParam(':name', $taskName);
        $stmt->bindParam(':description', $taskDescription);
        $stmt->bindParam(':due_date', $taskDueDate, PDO::PARAM_INT);
        $stmt->bindParam(':image', $taskImageBlob, PDO::PARAM_LOB);
        $stmt->bindParam(':user_id', $_COOKIE['userID'], PDO::PARAM_INT);
        try {
            $stmt->execute();
            echo 'Task created successfully';
            header('Location: /');
        } catch (PDOException $e) {
            var_dump($e);
            echo 'Task creation failed';
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id']) && $connected) {
    if (str_starts_with($_SERVER['REQUEST_URI'], '/complete')) {
        echo 'hello complete';
        $query = "UPDATE task SET completed = 1 where id=$_GET[id]";
        $stmt = $pdo->prepare($query);
        try {
            $stmt->execute();
            echo 'Task set as completed successfully';
            header('Location: /');
        } catch (PDOException $e) {
            var_dump($e);
        }
    } else if (str_starts_with($_SERVER['REQUEST_URI'], '/delete-task')) {
        $query = "DELETE FROM task WHERE id = {$_GET['id']} AND user_id = {$_COOKIE['userID']}";
        $stmt = $pdo->prepare($query);
        try {
            $stmt->execute();
            header('Location: /');
        } catch (PDOException $e) {
            echo "Task deletion failed, probably because you don't have permission to delete a task that isn't your :).";
        }

    }
}

?>

<html lang="en">
<head>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <title>NTask Manager</title>
</head>
<body class="vh-100">
<header>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="/"> <img src="./ntask.png" width="60px"> NTask </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav"> <?php if (!$connected) { ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="/">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/login">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register">Register</a>
                        </li>
                    <?php } else { ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="/">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/create">Create task</a>
                        </li>
                        <?php if ($admin) { ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/panel">Admin panel</a>
                            </li>
                        <?php } ?>

                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-danger" href="/logout">Logout</a>
                        </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </nav>
</header>

<?php if ($login) { ?>
    <?php if (isset($_COOKIE['userID'])) {
        header('Location: /');
    } ?>
    <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
        <h1 class="text-center">Login</h1>
        <?php if (!empty($user_login_status) && $user_login_status == 'Invalid username or password') { ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $user_login_status; ?>
            </div>
        <?php } else if (!empty($user_login_status)) { ?>
            <div class="alert alert-success" role="alert">
                <?php echo $user_login_status; ?>
            </div>
        <?php } ?>
        <div class="container d-flex justify-content-center align-items-center w-100">
            <form action="/login" method="post">
                <div class="mb-3">
                    <label for="exampleInputEmail1" class="form-label>">Username</label>
                    <input name="username" type="text" class="form-control" id="exampleInputUsername" required
                           aria-describedby="usernameHelp">
                </div>
                <div class="mb-3">
                    <label for="exampleInputPassword1" class="form-label>">Password</label>
                    <input name="password" type="password" class="form-control" id="exampleInputPassword1" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
<?php } elseif ($register) { ?>
    <?php if (isset($_COOKIE['userID'])) {
        header('Location: /');
    } ?>
    <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
        <h1 class="text-center">Create a new account</h1>
        <?php if (!empty($user_registration_status)) { ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $user_registration_status; ?>
            </div>
        <?php } ?>
        <div class="container d-flex justify-content-center align-items-center w-100">
            <form action="/register" method="post">
                <div class="mb-3">
                    <label for="exampleInputEmail1" class="form-label>">Username</label>
                    <input name="username" type="text" class="form-control" id="exampleInputUsername" required
                           aria-describedby="usernameHelp">
                </div>
                <div class="mb-3">
                    <label for="exampleInputPassword1" class="form-label>">Password</label>
                    <input name="password" type="password" class="form-control" id="exampleInputPassword1" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label>">Email</label>
                    <input name="email" type="email" class="form-control" id="exampleInputPassword1" required>
                </div>
                <button type="submit" class="btn btn-primary">Register</button>
            </form>
        </div>
    </div>
<?php } elseif (!$connected) { ?>
    <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
        <?php if (isset($_COOKIE['logged_out_message'])) { ?>
            <div class="alert alert-success" role="alert">
                <?php echo $_COOKIE['logged_out_message']; ?>
            </div>
        <?php } ?>
        <h1 class="text-center"><img src="./ntask.png">Welcome to NTask.</h1>
        <p class="text-center">Please <a href="/login"> login</a> or <a href="/register"> register</a> to continue.</p>
    </div>
<?php } elseif ($allow_panel) { ?>
    <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
        <h1> Admin panel </h1>
        <p> Welcome to the admin panel, <strong><?php echo $current_user; ?>.</strong></p>
        <div>
            <h3> Users </h3>
            <table class="table">
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Username</th>
                    <th scope="col">Email</th>
                    <th scope="col">Admin</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pdo->query("SELECT * FROM user") as $user) { ?>
                    <tr>
                        <th scope="row"><?php echo $user['id']; ?></th>
                        <td><?php echo $user['username']; ?></td>
                        <td><?php echo $user['email']; ?></td>
                        <td style="<?php echo $user['admin'] == 0 ? "color: #721c24 !important; background-color:#f8d7da !important" : "color: #1E6B2ECC !important; background-color:#B4F8C3CC !important"; ?>; font-weight: bold"><?php echo $user['admin'] == 1 ? "YES" : "NO"; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
<?php } else { ?>
    <?php if ($_SERVER['REQUEST_URI'] == "/create") { ?>
        <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
            <h1 class="text-center"> Create a new task </h1>
            <p class="small"> (*) fields are mandatory.</p>
            <form action="/create-task" method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="taskName" class="form-label">Name of the task (*)</label>
                    <input name="taskName" type="text" class="form-control" id="taskName" required>
                </div>
                <div class="mb-3">
                    <label for="taskDescription" class="form-label">Description of the task</label>
                    <input name="taskDescription" type="text" class="form-control" id="taskDescription">
                </div>
                <div class="mb-3">
                    <label for="taskDueDate" class="form-label">Due date (*)</label>
                    <input name="taskDueDate" type="datetime-local" class="form-control" id="taskDueDate" required>
                </div>
                <div class="mb-3">
                    <label for="taskImage" class="form-label">Image (for style)</label>
                    <input name="taskImage" type="file" class="form-control" id="taskImage">
                </div>
                <button type="submit" class="btn btn-success">Create task</button>
            </form>
        </div>
    <?php } else { ?>
        <div class="d-flex flex-column justify-content-center align-items-center" style="min-height:90vh">
            <h1 class="text-center"><img src="./ntask.png">Welcome to NTask.</h1>
            <p class="text-center">You are logged in as
                <strong><?php echo isset($current_user) ? $current_user : "Stranger..."; ?></strong></p>
            <?php if ($user['admin'] == 1) { ?>
                <div class="d-flex flex-column justify-content-center align-items-center alert alert-warning"
                     style="border:1px solid rgba(0,0,0,0.175); border-radius: 0.375rem; padding:16px">
                    <p class="text-center">You are an administrator.</p>
                    <a href="/panel" class="btn btn-warning"> Go to panel </a>
                </div>
            <?php } ?>
            <hr style="background-color:black">
            <h4> Scroll down to access all your tasks, or create one.</h4>
            <a href="/create" class="btn btn-success"> Create a new task </a>
            <hr>
            <div class="container d-flex align-items-center w-100 flex-wrap"
                 style="border: solid 1px rgba(0,0,0,0.175); border-radius:0.375rem; background-color: rgba(0,0,0,0.020">
                <?php foreach ($pdo->query("SELECT * FROM task WHERE user_id = " . $_COOKIE['userID'] . " ORDER BY due_date") as $task) { ?>
                    <div class="card m-2 <?php if (strtotime($task['due_date']) < time() && $task['completed'] == 0) {
                        echo "due-passed";
                    } else if ($task['completed'] == 1) {
                        echo "task-complete";
                    } ?>"
                         style="width: 18rem;">
                        <img src="data:image/jpg;base64, <?php echo base64_encode($task['image']); ?>"
                             class="card-img-top" alt="...">
                        <div class="card-body">
                            <?php if (strtotime($task['due_date']) < time() && $task['completed'] == 0) echo "<h6 style='background-color: #eea8ad; border-radius:0.375rem; padding:8px'> Due date passed! </h6>" ?>
                            <h5 class="card-title">
                                <?php echo $task['name']; ?>
                            </h5>
                            <p class="card-text">
                                <?php echo $task['description']; ?>
                            </p>
                            <?php if ($task['completed'] == 1) { ?>
                                <p class="card-text"
                                   style="background-color: rgba(135,227,152,0.8); padding:8px; border-radius:0.375rem">
                                    Completed
                                </p>
                            <?php } else { ?>
                                <p class="card-text">
                                    Due date: <?php echo date('d/m/Y H:i', strtotime($task['due_date'])); ?>
                                </p>
                                <a href="/complete?id=<?php echo $task['id']; ?>" class="btn btn-info">Set as
                                    complete</a>
                            <?php } ?>
                            <a href="/delete-task?id=<?php echo $task['id']; ?>" class="btn btn-danger">Delete</a>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
<?php } ?>

</body>
</html>