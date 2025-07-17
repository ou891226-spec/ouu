<!DOCTYPE html>
<html>
<head>
    <title>註冊表單</title>
    <link href="css/register.css" rel="stylesheet" type="text/css">
</head>
<body>
    <h2>註冊</h2>

    <?php
    if (isset($_GET['error'])) {
        echo "<p style='color:red'>" . $_GET['error'] . "</p>";
    }
    ?>

    <form action="register.php" method="post">
        姓名: <input type="text" name="name"><br><br>
        帳號: <input type="text" name="id"><br><br>
        請輸入密碼: <input type="password" name="password"><br><br>
        再輸入一次密碼: <input type="password" name="confirm_password"><br><br>

        <input type="submit" value="註冊使用者">
    </form>
</body>
</html>
