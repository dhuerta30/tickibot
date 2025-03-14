<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?=$_ENV["APP_NAME"]?> | Reset</title>
	<!-- Google Font: Source Sans Pro -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?=$_ENV["BASE_URL"]?>theme/plugins/fontawesome-free/css/all.min.css">
</head>
<body>
<style>
    body {
        background: #5d6d7e!important;
    }
    li.list-group-item.bg-primary.text-white.text-center {
        font-size: 20;
        font-weight: 500;
    }
</style>
<div class="container">
    <div class="row mt-5">
        <div class="col-md-6 m-auto">
            
        <div class="card">
        <ul class="list-group list-group-flush">
            <li class="list-group-item bg-primary text-white text-center">Recuperar Contraseña</li>
            <div class="row">
                <?php 
                    $configuracion = App\Controllers\HomeController::configuracion();
                ?>
                <div class="col-md-12 text-center">
                    <img src="<?=$configuracion[0]["logo_login"]?>" width="150">
                </div>
            </div>
            <li class="list-group-item bg-white">
            <?= $reset; ?>
            </li>
            <li class="list-group-item bg-primary"><a href="<?=$_ENV["BASE_URL"]?>login" class="text-white">Acceder</a></li>
        </ul>
        </div>

        </div>
    </div>
</div>
<div id="artify-ajax-loader">
    <img width="300" src="<?=$_ENV["BASE_URL"]?>app/libs/artify/images/ajax-loader.gif" class="artify-img-ajax-loader"/>
</div>
</body>
</html>