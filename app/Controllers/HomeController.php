<?php

namespace App\Controllers;

use App\core\SessionManager;
use App\core\Token;
use App\core\Request;
use App\core\View;
use App\core\Redirect;
use App\core\DB;
use Coderatio\SimpleBackup\SimpleBackup;
use App\Models\DatosPacienteModel;
use App\Models\PageModel;
use App\Models\UsuarioMenuModel;
use App\Models\UserModel;
use App\Models\ProcedimientoModel;
use App\Models\UsuarioSubMenuModel;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use App\Core\APIClient;
use Artify;

class HomeController
{
    public $token;

	public function __construct()
	{
		SessionManager::startSession();
		$Sesusuario = SessionManager::get('usuario');
		if (isset($Sesusuario)) {
			if ($_SERVER['REQUEST_URI'] === "/home/modulos") {
				Redirect::to("modulos");
			}
		} else {
			Redirect::to("login");
		}
        $this->token = Token::generateFormToken('send_message');
	}

	public static function Obtener_menus(){
		$usuario_menu = new UsuarioMenuModel();
		$data_usuario_menu = $usuario_menu->Obtener_menus();
		return $data_usuario_menu;
	}

	public static function obtener_menu_por_id_usuario($id_usuario){
		$usuario_menu = new UsuarioMenuModel();
		$data_usuario_menu = $usuario_menu->Obtener_menu_por_id_usuario($id_usuario);
		return $data_usuario_menu;
	}

	public static function Obtener_submenus($id_menu){
		$usuario_submenu = new UsuarioSubMenuModel();
		$data_usuario_submenu = $usuario_submenu->Obtener_submenus($id_menu);
		return $data_usuario_submenu;
	}

	public static function Obtener_submenu_por_id_menu($id_menu, $id_usuario){
		$usuario_submenu = new UsuarioSubMenuModel();
		$data_usuario_submenu = $usuario_submenu->Obtener_submenu_por_id_menu($id_menu, $id_usuario);
		return $data_usuario_submenu;
	}

	public function obtener_menu_usuario()
	{
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$userId = $request->post('userId');

			$data_usuario_menu = HomeController::obtener_menu_por_id_usuario($userId);

			$usuario = new UserModel();
			$data_user = $usuario->obtener_usuario_porId($userId);

			$html = '<ul class="list-none">
				<li>
					<input type="checkbox" value="select-all" name="select_all" class="select-all">
					<span>Marcar Todos / Desmarcar Todos</span>
				</li>
			</ul>';
			$html .= '<ul class="list-none">';
			$html .= '<span>Menus Asignados a ' . $data_user[0]["nombre"] . '</span><br><br>';

			foreach ($data_usuario_menu as $item) {
				$html .= '<li>';

				if ($item["submenu"] == "Si") {
					$isChecked = ($item['visibilidad_menu'] == 'Mostrar' && $item['id_usuario'] ? 'checked' : ''); // Verificar si el menú está asignado al usuario
					$html .= '<input type="checkbox" ' . $isChecked . ' id="' . $item['id_menu'] . '" class="menu-checkbox-pr mr-2" data-type="menu">';
					$html .= '<span><i class="' . $item['icono_menu'] . '"></i> ' . $item['nombre_menu'] . '</span>';
					$html .= '<ul class="list-none">';

					$data_usuario_submenu = HomeController::Obtener_submenu_por_id_menu($item["id_menu"], $userId);

					foreach ($data_usuario_submenu as $submenu) {

						$isCheckedSubmenu = ($submenu['visibilidad_submenu'] == 'Mostrar' && $submenu['id_usuario'] ? 'checked' : ''); // Verificar si el submenu está asignado al usuario
						$html .= '<li>';
						$html .= '<input type="checkbox" ' . $isCheckedSubmenu . ' id="' . $submenu['id_submenu'] . '" class="submenu-checkbox-pr mr-2" data-type="menu" data-parent="'.$item['id_menu'].'">';
						$html .= '<span><i class="' . $submenu['icono_submenu'] . '"></i> ' . $submenu['nombre_submenu'] . '</span>';
						$html .= '</li>';
					}

					$html .= '</ul>';
				} else {
					$isChecked = ($item['visibilidad_menu'] == 'Mostrar' && $item['id_usuario'] ? 'checked' : ''); // Verificar si el menú está asignado al usuario
					$html .= '<input type="checkbox" ' . $isChecked . ' id="' . $item['id_menu'] . '" class="menu-checkbox-pr mr-2" data-type="menu">';
					$html .= '<span><i class="' . $item['icono_menu'] . '"></i> ' . $item['nombre_menu'] . '</span>';
				}

				$html .= '</li>';
			}

			$html .= '<div class="row mt-4">
						<div class="col-md-12">
							<a href="javascript:;" title="Actualizar" class="btn btn-success btn-sm asignar_menu_usuario" data-id="' . $userId . '"><i class="far fa-save"></i> Actualizar</a>
						</div>
					</div>';
			$html .= '</ul>';
			$checkbox =  $html;
			HomeController::modal("menus", "<i class='far fa-eye'></i> Actualizar Menus Asignados", $checkbox);
		}
	}


	public function cargar_imagenes_configuracion(){
		$request = new Request();
	
		if ($request->getMethod() === 'POST') {
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$data = $queryfy->select("configuracion");
			
			echo json_encode($data);
		}
	}
	
	public function refrescarMenu()
	{
		$request = new Request();
	
		if ($request->getMethod() === 'POST') {
			// Obtén la URL actual
			$currentUrl = $_SERVER['REQUEST_URI'];
			$id_sesion_usuario = $_SESSION["usuario"][0]["id"];

			// Obtén el menú y submenús utilizando funciones existentes
			$menu = HomeController::obtener_menu_por_id_usuario($id_sesion_usuario);

			// Estructura para almacenar el menú
			$menuHtml = '<nav class="mt-2">
							<ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">';

			foreach ($menu as $item) {
				if ($_SESSION["usuario"][0]["idrol"] == 1 || $item["nombre_menu"] != "usuarios" && $item["visibilidad_menu"] != "Ocultar") {
					// Obtiene submenús
					$submenus = HomeController::Obtener_submenu_por_id_menu($item['id_menu'], $id_sesion_usuario);
					$tieneSubmenus = ($item["submenu"] == "Si");
					$subMenuAbierto = false;

					// Verifica si algún submenú está activo
					foreach ($submenus as $submenu) {
						if (strpos($currentUrl, $submenu['url_submenu']) !== false) {
							$subMenuAbierto = true;
							break;
						}
					}

					$menuHtml .= '<li class="nav-item' . ($subMenuAbierto ? ' menu-is-opening menu-open' : '') . '">';
					if ($tieneSubmenus) {
						$menuHtml .= '<a href="javascript:;" class="nav-link' . (strpos($currentUrl, $submenu['url_submenu']) !== false ? ' active' : '') . '">
										<i class="' . $item['icono_menu'] . '"></i>
										<p>
											' . $item['nombre_menu'] . '
											<i class="right fas fa-angle-left"></i>
										</p>
									</a>
									<ul class="nav nav-treeview" style="' . ($subMenuAbierto ? 'display: block;' : '') . '">';
						foreach ($submenus as $submenu) {
							if ($submenu["visibilidad_submenu"] != "Ocultar") {
								$menuHtml .= '<li class="nav-item">
												<a href="' . rtrim($_ENV["BASE_URL"], '/') . $submenu['url_submenu'] . '" class="nav-link' . (strpos($currentUrl, $submenu['url_submenu']) !== false ? ' active' : '') . '">
													<i class="' . $submenu['icono_submenu'] . '"></i>
													<p>' . $submenu['nombre_submenu'] . '</p>
												</a>
											</li>';
							}
						}
						$menuHtml .= '</ul>';
					} else {
						if($item["visibilidad_menu"] != "Ocultar"){
						$menuHtml .= '<a href="' . rtrim($_ENV["BASE_URL"], '/') . $item['url_menu'] . '" class="nav-link' . (strpos($currentUrl, $item['url_menu']) !== false ? ' active' : '') . '">
										<i class="' . $item['icono_menu'] . '"></i>
										<p>' . $item['nombre_menu'] . '</p>
									</a>';
						}
					}
					$menuHtml .= '</li>';
				}
			}

			$menuHtml .= '</ul>
						</nav>';

			// Retorna el HTML del menú
			echo json_encode([$menuHtml]);
		}
	}


	public function asignar_menus_usuario()
	{
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$userId = $request->post("userId");
			$selectedMenus = $request->post("selectedMenus");

			if (is_array($selectedMenus)) {
				$artify = DB::ArtifyCrud();
				$queryfy = $artify->getQueryfyObj();

				$menuMarcado = false;
				$menuDesmarcado = false;

				foreach ($selectedMenus as $menu) {
					$menuId = $menu["menuId"];
					$submenuIds = isset($menu["submenuIds"]) ? $menu["submenuIds"] : [];
					$checked = $menu["checked"];

					// Procesar el menú principal
					$existMenu = $queryfy->where('id_menu', $menuId)
						->where('id_usuario', $userId)
						->select('usuario_menu');

					switch ($checked) {
						case "true":
							if (!$existMenu) {
								$queryfy->insert('usuario_menu', array(
									"id_usuario" => $userId,
									"id_menu" => $menuId,
									"visibilidad_menu" => "Mostrar"
								));
								$menuMarcado = true;
							} else {
								$queryfy->where('id_usuario', $userId)
									->where('id_menu', $menuId)
									->update('usuario_menu', array("visibilidad_menu" => "Mostrar"));
								$menuMarcado = true;
							}
							break;

						case "false":
							$queryfy->where('id_usuario', $userId)
								->where('id_menu', $menuId)
								->update('usuario_menu', array("visibilidad_menu" => "Ocultar"));
							$menuDesmarcado = true;
							break;
					}

					// Procesar los submenús asociados al menú principal
					foreach ($submenuIds as $submenuId) {
						$id_submenu = $submenuId['id'];
						$checked = $submenuId["checked"];

						$existSubmenu = $queryfy->where('id_submenu', $id_submenu)
							->where('id_usuario', $userId)
							->select('usuario_submenu');

						switch ($checked) {
							case "true":
								if (!$existSubmenu) {
									$queryfy->insert('usuario_submenu', array(
										"id_usuario" => $userId,
										"id_submenu" => $id_submenu,
										"id_menu" => $menuId,
										"visibilidad_submenu" => "Mostrar"
									));
								} else {
									$queryfy->where('id_usuario', $userId)
										->where('id_submenu', $id_submenu)
										->where('id_menu', $menuId)
										->update('usuario_submenu', array("visibilidad_submenu" => "Mostrar"));
								}
								break;

							case "false":
								$queryfy->where('id_usuario', $userId)
									->where('id_submenu', $id_submenu)
									->where('id_menu', $menuId)
									->update('usuario_submenu', array("visibilidad_submenu" => "Ocultar"));
								break;
						}
					}
				}

				$response = [];

				if ($menuMarcado) {
					$response['success'][] = 'Menús asignados correctamente';
				}

				if ($menuDesmarcado) {
					$response['success'][] = 'Menús Actualizados correctamente';
				}

				if (!$menuMarcado && !$menuDesmarcado) {
					$response['error'][] = 'Todos los menús ya fueron asignados previamente';
				}

				echo json_encode($response);
			} else {
				echo json_encode(['error' => 'Debe seleccionar al menos 1 menú de la lista para continuar']);
			}
		}
	}


	public function acceso_menus(){
		$artify = DB::ArtifyCrud();
		$artify->colRename("idrol", "Rol");
		$artify->colRename("id", "ID");
		$artify->relatedData('idrol','rol','idrol','nombre_rol');
		$artify->tableColFormatting("avatar", "html",array("type" =>"html","str"=>'<img width="50" src="'.$_ENV["BASE_URL"].'app/libs/artify/uploads/{col-name}">'));
		$artify->crudRemoveCol(array("rol","estatus","password", "token", "token_api", "expiration_token"));
		$artify->setSearchCols(array("id","nombre","email", "usuario", "idrol"));
		$artify->setSettings("searchbox", true);
		$artify->setSettings("addbtn", false);
		$artify->setSettings("viewbtn", false);
		$artify->setSettings('editbtn', true);
		$artify->setSettings('delbtn', true);
		$artify->setSettings("printBtn", false);
		$artify->setSettings("pdfBtn", false);
		$artify->setSettings("csvBtn", false);
		$artify->setSettings("excelBtn", false);
		$artify->setSettings("function_filter_and_search", true);
		$artify->setSettings("template", "acceso_usuarios_menus");
		$artify->setSettings("deleteMultipleBtn", false);
		$artify->setSettings("checkboxCol", false);
		$render = $artify->dbTable("usuario")->render();

		View::render(
			'acceso_menus',[
				'render' => $render
			]
		);
	}

	public function usuarios()
	{
		if($_SESSION["usuario"][0]["idrol"] == 1){
            $token = $this->token;
			$artify = DB::ArtifyCrud();
			$artify->fieldCssClass("id", array("d-none"));
			$artify->tableHeading("Lista de usuarios");
            $artify->formStaticFields("token_form", "html", "<input type='hidden' name='auth_token' value='" . $token . "' />");
			$artify->tableColFormatting("avatar", "html",array("type" =>"html","str"=>'<img width="50" src="'.$_ENV["BASE_URL"].'app/libs/artify/uploads/{col-name}">'));
			$artify->fieldDataAttr("password", array("value"=>"", "placeholder" => "*****", "autocomplete" => "new-password"));
			$artify->formDisplayInPopup();
			$artify->fieldGroups("Name",array("nombre","email"));
			$artify->fieldGroups("Name2",array("usuario","password"));
			$artify->fieldGroups("Name3",array("idrol","avatar"));
			$artify->setSettings("searchbox", true);
			$artify->setSettings("required", false);
			$artify->setSettings("checkboxCol", false);
			$artify->setSettings("refresh", false);
			$artify->setSettings("function_filter_and_search", true);
			$artify->setSettings('editbtn', true);    
            $artify->setSettings('delbtn', true);
			$artify->setSettings("deleteMultipleBtn", false);
			$artify->colRename("id", "ID");
			$artify->colRename("idrol", "Rol");
			$artify->colRename("email", "Correo");
			$artify->fieldHideLable("id");
			$artify->addCallback("before_insert", "insetar_usuario");
			$artify->addCallback("before_update", "editar_usuario");
			$artify->crudRemoveCol(array("rol","estatus","password", "token", "token_api", "expiration_token"));
			$artify->setSearchCols(array("id","nombre","email", "usuario", "idrol"));
			$artify->where("estatus", 1);
			$artify->recordsPerPage(5);
			$artify->fieldTypes("avatar", "FILE_NEW");
			$artify->fieldTypes("password", "password");
			$artify->fieldRenameLable("nombre", "Nombre Completo");
			$artify->fieldRenameLable("email", "Correo electrónico");
			$artify->fieldRenameLable("password", "Clave de acceso");
			$artify->fieldRenameLable("idrol", "Tipo Usuario");
			$artify->setSettings("viewbtn", false);
			$artify->setSettings("hideAutoIncrement", false);
			$artify->setSettings("template", "usuarios");
			$artify->buttonHide("submitBtnSaveBack");
			$artify->formFields(array("id","nombre","email","password","usuario", "idrol", "avatar"));
			$artify->setRecordsPerPageList(array(5, 10, 15, 'All'=> 'Todo'));
			$artify->setSettings("printBtn", false);
			$artify->setSettings("pdfBtn", true);
			$artify->setSettings("csvBtn", false);
			$artify->setSettings("excelBtn", true);
			$artify->relatedData('idrol','rol','idrol','nombre_rol');
			$render = $artify->dbTable("usuario")->render();

			View::render(
				'home',
				['render' => $render]
			);
		} else {
			Redirect::to("home/datos_paciente");
		}
	}


	public function generar_datos_usuario(){
		
		$request = new Request();

    	if ($request->getMethod() === 'POST') {
			$usuario = $_SESSION["usuario"];
			echo json_encode(['usuario' => $usuario]);
		}
	}

	public function generar_edad(){
		
		$request = new Request();

    	if ($request->getMethod() === 'POST') {
			$fecha_nac = $request->post("fecha_nac");

			if(!empty($fecha_nac)){
				$fechaNacimiento = HomeController::calcularFechaNacimiento($fecha_nac);
				if($fechaNacimiento >= 0){
					echo json_encode(['fecha_nacimiento' => $fechaNacimiento]);
				} else {
					echo json_encode(['error' => 'La fecha de nacimiento no se pudo calcular, ingrese una mas antigua']);
				}
			}
		}
	}

	public static function calcularFechaNacimiento($fecha_nac){
		$fecha_nac = strtotime($fecha_nac);
		$edad = date('Y', $fecha_nac);
		if (($mes = (date('m') - date('m', $fecha_nac))) < 0) {
			$edad++;
		} elseif ($mes == 0 && date('d') - date('d', $fecha_nac) < 0) {
			$edad++;
		}
		return date('Y') - $edad;
	}

	
	public static function modal($id, $titulo, $contenido = ""){
		$modal = '<div class="modal fade" id="'.$id.'" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-modal="true">
			<div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="exampleModalLabel">'.$titulo.'</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						'.$contenido.'
					</div>
				</div>
			</div>
		</div>';
		echo $modal;
	}

	public function respaldos(){
		$respaldos = DB::ArtifyCrud();
        $respaldos->tableHeading("Respaldos");
        $respaldos->fieldTypes("file", "file");
        $respaldos->dbOrderBy("hora desc");
		$respaldos->tableColFormatting("fecha", "date",array("format" =>"d/m/Y"));
		$respaldos->setSearchCols(array("usuario", "fecha", "hora"));
        $respaldos->tableColFormatting("archivo", "html", array("type" => "html", "str" => "<a class='btn btn-success btn-sm' href=\"".$_ENV["BASE_URL"]."app/libs/artify/uploads/{col-name}\" data-attribute=\"abc-{col-name}\"><i class=\"fa fa-download\"></i> Descargar Respaldo</a>"));
        $respaldos->setSettings("searchbox", true);
		$respaldos->setSettings("addbtn", false);
		$respaldos->setSettings('editbtn', true);    
		$respaldos->setSettings('delbtn', true);
        $respaldos->setSettings("viewbtn", false);
		$respaldos->setSettings("function_filter_and_search", true);
        $respaldos->setSettings("printBtn", false);
        $respaldos->setSettings("pdfBtn", false);
        $respaldos->setSettings("csvBtn", false);
        $respaldos->setSettings("excelBtn", false);
		$respaldos->setSettings("refresh", false);
		$respaldos->fieldTypes("archivo", "FILE_NEW");
		$respaldos->enqueueBtnTopActions("Report export",  "<i class='fa fa-database'></i> Generar Respaldo", "javascript:;", array(), "btn-report btn btn-success");
		$respaldos->crudRemoveCol(array("id"));
        $respaldos->addCallback("before_delete", "delete_file_data");
        $respaldos->addFilter("UserFilter", "Filtrar por Usuario que generó el respaldo", "usuario", "dropdown");
        $respaldos->setFilterSource("UserFilter", "backup", "usuario", "usuario as pl", "db");
        $respaldos->addFilter("DateFilter", "Filtrar por Fecha", "fecha", "dropdown");
        $respaldos->setFilterSource("DateFilter", "backup", "fecha", "fecha as pl", "db");
        $respaldos->addFilter("HourFilter", "Filtrar por Hora", "hora", "dropdown");
        $respaldos->setFilterSource("HourFilter", "backup", "hora", "hora as pl", "db");

        $render_respaldos = $respaldos->dbTable("backup")->render();

		View::render(
			"respaldos", [
				'render' => $render_respaldos
			]
		);
	}

	public function export_db()
	{
		if($_SERVER['REQUEST_METHOD'] === 'POST'){
			date_default_timezone_set("America/Santiago");
			$date = date('Y-m-d');
			$hour = date('G:i:s');
			$user = $_SESSION['usuario'][0]["usuario"];

			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$id = $queryfy->select("backup");

			$exportDirectory = realpath(__DIR__ . '/../libs/artify/uploads');

			// Verificar si el directorio existe y, si no, intentar crearlo
			if (!is_dir($exportDirectory) && !mkdir($exportDirectory, 0777, true)) {
				die('Error al crear el directorio de exportación');
			}

			$simpleBackup = SimpleBackup::setDatabase([
				$_ENV['DB_NAME'],
				$_ENV['DB_USER'],
				$_ENV['DB_PASS'],
				$_ENV['DB_HOST']
			])->storeAfterExportTo($exportDirectory, "procedimiento" . time() . ".sql");

			$file = $_ENV["BASE_URL"] . $_ENV['UPLOAD_URL'] . $simpleBackup->getExportedName();

			$queryfy->insert("backup", array("archivo" => basename($file), "fecha" => $date, "hora" => $hour, "usuario" => $user));

			echo json_encode(['file' => $file, 'success' => 'Tus datos se han respaldado con éxito ']);
		}
	}

	public static function validaRut($rut)
    {
        if (strpos($rut, "-") == false) {
            $RUT[0] = substr($rut, 0, -1);
            $RUT[1] = substr($rut, -1);
        } else {
            $RUT = explode("-", trim($rut));
        }
        $elRut = $RUT[0];
        $factor = 2;
        $suma = 0;
        for ($i = strlen($elRut) - 1; $i >= 0; $i--) {
            $factor = $factor > 7 ? 2 : $factor;
            $suma += $elRut[$i] * $factor++;
        }
        $resto = $suma % 11;
        $dv = 11 - $resto;
        if ($dv == 11) {
            $dv = 0;
        } else if ($dv == 10) {
            $dv = "k";
        } else {
            $dv = $dv;
        }
        if ($dv == trim(strtolower($RUT[1]))) {
            return true;
        } else {
            return false;
        }
    }

	public static function menuDB(){
		$artify = DB::ArtifyCrud();
		$queryfy = $artify->getQueryfyObj();
		$queryfy->orderBy(array("orden_menu asc"));
		$data = $queryfy->select("menu");
		return $data;
	}

	public static function submenuDB($idMenu){
		$artify = DB::ArtifyCrud();
		$queryfy = $artify->getQueryfyObj();
		$queryfy->where("id_menu", $idMenu, "=");
		$queryfy->orderBy(array("orden_submenu asc")); // Ajusta el nombre de la columna de ordenación si es diferente
		$data = $queryfy->select("submenu");
		return $data;
	}	

	public function modulos()
	{
		$html_template_tablas = '
		<div class="row">
			<div class="col-md-12">
				<div class="form-group">
					<label class="form-label">Nombre Tabla Base de Datos:</label>
					{nombre_tabla}
					<p class="artify_help_block help-block form-text with-errors"></p>
				</div>
			</div>
			<div class="col-md-12" id="columnsContainer">
				<div class="form-group column-group">
					<label class="form-label">Definir Columnas:</label>
					<div class="row">
						<div class="col-md">
							<input type="text" class="columnName form-control" placeholder="Nombre de la columna (Ej: id)">
						</div>
						<div class="col-md">
							<select class="columnType form-control tipo">
								<option value="">Seleccionar</option>
								<option value="INT">Entero</option>
								<option value="VARCHAR">Carácteres</option>
								<option value="TEXT">Texto</option>
								<option value="LONGTEXT">Texto Largo</option>
								<option value="DATE">Fecha</option>
								<option value="TIME">Hora</option>
								<option value="DATETIME">Fecha y Hora</option>
								<option value="TIMESTAMP">Marca de Tiempo</option>
								<option value="YEAR">Año</option>
							</select>
						</div>
						<div class="col-md oculto d-none">
							<input type="text" class="longitud form-control">
						</div>
						<div class="col-md">
							<select class="columnNull form-control">
								<option value="">Seleccionar</option>	
								<option value="NOT NULL">NOT NULL</option>
								<option value="NULL">NULL</option>
							</select>
						</div>
						 <div class="col-md-12 mt-2">
							<div class="primary-options">
								<label>
									<input type="checkbox" class="primaryKey" /> 
									<p>Clave Primaria</p>
								</label>
								<label>
									<input type="checkbox" class="autoIncrement" /> 
									<p>Autoincremental</p>
								</label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-12">
				<a href="javascript:;" class="mt-3 mb-4 btn btn-primary addColumn">Agregar otra columna</a>
			</div>
			<div class="col-md-12">
				<div class="form-group">
					<label class="form-label">Consulta SQL:</label>
					{query_tabla}
					<p class="artify_help_block help-block form-text with-errors"></p>
				</div>
			</div>
			<div class="col-md-12">
				<div class="row mt-4">
					<div class="col-md-12 text-center">
						<button type="button" class="btn btn-danger artify-form-control artify-button artify-back regresar_modulos" data-action="back">Regresar</button> 
						<input type="submit" class="btn btn-primary artify-form-control artify-submit" data-action="insert" value="Guardar">
					</div>
				</div>
			</div>
		</div>
		';

		$tablas = DB::ArtifyCrud();
		$tablas->set_template($html_template_tablas);
		$tablas->fieldRenameLable("caracteres", "Caracteres");
		$tablas->setLangData("add", "Agregar Tabla");
		$tablas->setLangData("add_row", "Agregar Campos");
		$tablas->formFields(array("nombre_tabla", "query_tabla"));
		$tablas->editFormFields(array("nombre_tabla", "modificar_tabla", "tabla_modificada"));
		$tablas->setSearchCols(array("nombre_tabla", "tabla_modificada"));
		$tablas->setSettings("searchbox", true);
		$tablas->setSettings("editbtn", false);
		$tablas->setSettings("delbtn", true);
		//$tablas->addWhereConditionActionButtons("delete", "nombre_tabla", "!=", array("configuracion"));

		$tablas->setSettings("template", "crear_tablas");
		$tablas->setSettings("function_filter_and_search", true);
		$tablas->fieldHideLable("tabla_modificada");
		$tablas->fieldDataAttr("tabla_modificada", array("style"=>"display:none", "value"=>"Si"));
		$tablas->fieldDataAttr("query_tabla", array("readonly"=>"true"));
		$tablas->crudRemoveCol(array("id_crear_tablas", "query_tabla", "modificar_tabla"));
		$tablas->fieldCssClass("nombre_tabla", array("nombre_tabla"));
		$tablas->fieldCssClass("query_tabla", array("query_tabla"));

		$tablas->buttonHide("submitBtn");
		$tablas->buttonHide("submitBtnBack");
		$tablas->buttonHide("cancel");

		$tablas->buttonHide("submitBtnSaveBack");
		$tablas->fieldAttributes("nombre_tabla", array("placeholder"=> "Nombre de la tabla de la base de datos"));
		$tablas->fieldAttributes("query_tabla", array("placeholder"=> "Rellena los campos de abajo para completar estos valores o ingresalos manualmente. Ejemplo: id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255)", "style"=> "min-height: 200px; max-height: 200px;"));
		$tablas->fieldRenameLable("modificar_tabla", "Modificar Campos de la tabla");
		$tablas->fieldRenameLable("query_tabla", "Consulta BD para crear Tabla");
		$tablas->colRename("query_tabla", "Consulta BD para crear Tabla");
		$tablas->addCallback("before_insert", "insertar_crear_tablas");
		$tablas->addCallback("before_update", "editar_crear_tablas");
		$tablas->addCallback("before_delete", "eliminar_crear_tablas");
		$render_tablas = $tablas->dbTable("crear_tablas")->render();

		$id_sesion_usuario = $_SESSION['usuario'][0]["id"];
		$artify = DB::ArtifyCrud(true);
		$artify->addPlugin("bootstrap-switch-master");
		
		$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

		$host = $_SERVER['HTTP_HOST'];

		$currentUrl = $scheme . '://' . $host . "/artify/";

		$html_template = '
		<div class="card">
		<div class="card-body bg-dark">
			<h5 class="card-title mb-0"><span class="titulo_modulo"></span> Generador de Módulos</h5>
		</div>
		<div class="card-body bg-light">

		<ul class="nav nav-pills flex-column flex-sm-row" id="myTab" role="tablist">
			<li class="nav-item border-0" role="presentation">
				<a class="nav-link active" id="modulos-tab" data-toggle="tab" href="#modulos" role="tab" aria-controls="modulos" aria-selected="true">Generador de Módulos</a>
			</li>
			<!--<li class="nav-item border-0" role="presentation">
				<a class="nav-link" id="pdf-tab" data-toggle="tab" href="#pdf" role="tab" aria-controls="pdf" aria-selected="false">Generador de PDF</a>
			</li>-->
		</ul>
	
		<div class="tab-content" id="myTabContent">
			<div class="tab-pane fade show active" id="modulos" role="tabpanel" aria-labelledby="modulos-tab">
			
				<div class="form mt-4">
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="form-label">Tipo de Módulo:</label>
								{crud_type}
								<p class="artify_help_block help-block form-text with-errors"></p>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label class="form-label">Nombre Tabla Base de Datos:</label>
								{tabla}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Si No posee tablas creelas en la Pestaña Crear Tablas y luego seleccionela acá</p>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label class="form-label">Nombre Módulo:</label>
								{nombre_modulo}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Ingrese Un Nombre para su Módulo</p>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 d-none">
							<div class="form-group">
								<label class="form-label">ID Tabla Base de Datos:</label>
								{id_tabla}
								<p class="artify_help_block help-block form-text with-errors"></p>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Nombre del Controlador:</label>
								{controller_name}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Cambie por su controlador o utilice el actual</p>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Nombre de La Vista:</label>
								{name_view}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Cambie por su vista o utilice la actual</p>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Nombre de Archivo de Devolución de llamada:</label>
								{file_callback}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Ingrese el nombre de su archivo de devolucion de llamada Ejemplo: funcion_personas</p>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="form-label">Tipo de Devolución de llamada:</label>
								{type_callback}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<a href="javascript:;" class="btn btn-info" id="limpiarSelect">Limpiar Selección</a>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label class="form-label">Consulta DB:</label>
								{query}
								<p class="artify_help_block help-block form-text with-errors"></p>
								<p style="font-size:14px;">Cambie por su consulta o utilice la actual</p>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<ul class="nav nav-pills flex-column flex-sm-row" id="myTab" role="tablist">
								<li class="nav-item border-0" role="presentation">
									<a class="nav-link active" id="joins-tab" data-toggle="tab" href="#joins" role="tab" aria-controls="joins" aria-selected="true">Unir Tablas y campos</a>
								</li>
								<li class="nav-item border-0 ocultar_opcion_filtros" role="presentation">
									<a class="nav-link" id="filtro-tab" data-toggle="tab" href="#filtro" role="tab" aria-controls="filtro" aria-selected="true">Filtro de Búsqueda</a>
								</li>
								<li class="nav-item border-0" role="presentation">
									<a class="nav-link" id="accionesgrilla-tab" data-toggle="tab" href="#accionesgrilla" role="tab" aria-controls="accionesgrilla" aria-selected="false">Acciones Grilla</a>
								</li>
								<li class="nav-item border-0" role="presentation">
									<a class="nav-link" id="camposformularios-tab" data-toggle="tab" href="#camposformularios" role="tab" aria-controls="camposformularios" aria-selected="false">Campos Formularios</a>
								</li>
							</ul>

							<div class="tab-content" id="myTabContent">
								<div class="tab-pane fade show active pb-3" id="joins" role="tabpanel" aria-labelledby="joins-tab">
									
									<div class="row pt-4">
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Activar Union Interna:</label>
												{activar_union_interna}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Tabla Principal Union:</label>
												{tabla_principal_union}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md esconder_tipo_union d-none">	
											<div class="form-group">
												<label class="form-label">Relación Principal Union:</label>
												{campos_relacion_union_tabla_principal}
												<p style="font-size: 14px;">Ejemplo: Campo Relacional id_personas</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Tabla secundaria Union:</label>
												{tabla_secundaria_union}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Relación Secundaria Union:</label>
												{campos_relacion_union_tabla_secundaria}
												<p style="font-size: 14px;">Ejemplo: Campo Relacional id_personas</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Activar Union Izquierda:</label>
												{activar_union_izquierda}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Tabla Principal Union:</label>
												{tabla_principal_union_izquierda}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md esconder_tipo_union_izquierda d-none">
											<div class="form-group">
												<label class="form-label">Relación Principal Union:</label>
												{campos_relacion_union_tabla_principal_izquierda}
												<p style="font-size: 14px;">Ejemplo: Campo Relacional id_personas</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>		
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Tabla secundaria Union:</label>
												{tabla_secundaria_union_izquierda}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md">
											<div class="form-group">
												<label class="form-label">Relación Secundaria Union:</label>
												{campos_relacion_union_tabla_secundaria_izquierda}
												<p style="font-size: 14px;">Ejemplo: Campo Relacional id_personas</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-primary cargar_datos_izquierda"><i class="fa fa-refresh"></i> Cargar Datos</a>
											</div>
										</div>
									</div>

								</div>
								<div class="tab-pane fade pb-3 ocultar_opcion_filtros" id="filtro" role="tabpanel" aria-labelledby="filtro-tab">
								
									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Filtro de Busqueda:</label>
												{active_filter}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
											<label class="form-label">Posición filtro Busqueda:</label>
											{posicion_filtro}
											<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Campos a Mostrar Filtro:</label>
												{mostrar_campos_filtro}
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarSelectFiltros">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3 esconder_tipo_filtro d-none">
											<div class="form-group">
												<label class="form-label">Tipo de Filtro:</label>
												{tipo_de_filtro}
												<p style="font-size: 14px;">Filtros soportados: casilla de verificacion, seleccion, fecha, texto</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

								</div>
								<div class="tab-pane fade" id="accionesgrilla" role="tabpanel" aria-labelledby="accionesgrilla-tab">
									
									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Búsqueda:</label>
												{active_search}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Popup:</label>
												{active_popup}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Eliminación Masiva:</label>
												{activate_deleteMultipleBtn}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Botón Agregar:</label>
												{button_add}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Campos a Mostrar en el buscador:</label>
												{mostrar_campos_busqueda}
												<span style="font-size: 14px;">seleccione la tabla para cargar estos campos</span>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarBuscador">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Columnas a Mostrar en la Grilla:</label>
												{mostrar_columnas_grilla}
												<span style="font-size: 14px;">seleccione la tabla para cargar estas columnas</span>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarGrilla">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Posición Botones de Acción Grilla:</label>
												{posicion_botones_accion_grilla}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Mostrar Columna Acciones Grilla:</label>
												{mostrar_columna_acciones_grilla}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Botón Refrescar Grilla:</label>
												{refrescar_grilla}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Clonar Filas:</label>
												{clone_row}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar funciones de Filtro y Búsqueda:</label>
												{function_filter_and_search}
												<span style="font-size: 14px;">Si Escoje la Opción "No" Deberá utilizar su propia lógica de Filtro y Búsqueda</span>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Mostrar Paginación:</label>
												{mostrar_paginacion}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Lista Registros por Página:</label>
												{activar_registros_por_pagina}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Cantidad de Registros por Página:</label>
												{cantidad_de_registros_por_pagina}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Botones de Exportación Grilla:</label>
												{actions_buttons_grid}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Posicionarse en la Página N°:</label>
												{posicionarse_en_la_pagina}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-4">
											<div class="form-group">
												<label class="form-label">Activar Numeración Columnas:</label>
												{activar_numeracion_columnas}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="form-label">Botones de Acción:</label>
												{buttons_actions}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-4">
											<div class="form-group">
												<label class="form-label">Ordenar Grilla por:</label>
												{ordenar_grilla_por}
												<p style="font-size:13px;">seleccione la tabla para cargar estos campos</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
	
											<div class="form-group">
												<label class="form-label">Tipo de Orden Grilla:</label>
												{tipo_orden}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Edición en Línea:</label>
												{activar_edicion_en_linea}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<!--<div class="col-md-12">
											<div class="form-group">
												<p class="text-center fwb">Renombrar columnas Grilla</p>
											</div>
										</div>-->
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Nombre Columnas:</label>
												{nombre_columnas}
												<p style="font-size:13px;">seleccione la tabla para cargar estas columnas</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarColumnas">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3 ocultar_nuevo_nombre_columnas">
											<div class="form-group">
												<label class="form-label">Nuevo Nombre Columnas:</label>
												{nuevo_nombre_columnas}
												<p style="font-size: 14px;">Escriba y presione enter para agregar los nuevos nombres</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Info Cantidad Registros por Página:</label>
												{totalRecordsInfo}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row mt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Texto cuando no hay Datos:</label>
												{text_no_data}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Autosugerencias en la Búsqueda:</label>
												{activar_autosugerencias}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

								</div>
								<div class="tab-pane fade pb-3" id="camposformularios" role="tabpanel" aria-labelledby="camposformularios-tab">

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Campos a Mostrar en el formulario Insertar:</label>
												{mostrar_campos_formulario}
												<span style="font-size: 14px;">seleccione la tabla para cargar estos campos</span>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarSelectInsertar">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Tipo de Campo:</label>
												{type_fields}
												<p style="font-size:13px;">Opciones: Imagen, Archivo, Radiobox, Checkbox, Combobox, Combobox Multiple, Campo de Texto, Campo de Área de Texto, Campo de Fecha, Campo de Fecha y Hora, Campo de Hora</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
											
											<div class="form-group">
												<label class="form-label">Valores Por defecto de Campos:</label>
												{valor_predeterminado_de_campo}
												<p class="artify_help_block help-block form-text with-errors"></p>
												<p style="font-size:13px;">Por ejemplo el valor que quiere que tenga un campo por defecto</p>
											</div>
										
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Campos a Mostrar en el formulario Editar:</label>
												{mostrar_campos_formulario_editar}
												<span style="font-size: 14px;">seleccione la tabla para cargar estos campos</span>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarSelectEditar">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Campos Requeridos de formulario:</label>
												{campos_requeridos}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Recaptcha:</label>
												{activar_recaptcha}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Site Key Recaptcha:</label>
												{sitekey_recaptcha}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Site Secret Recaptcha:</label>
												{sitesecret_repatcha}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Ocultar Id de la Tabla en Formularios</label>
												{ocultar_id_tabla}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row pt-4">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Nombre Campos:</label>
												{nombre_campos}
												<p style="font-size:13px;">seleccione la tabla para cargar estos campos</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarSelectCampos">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Nuevo Nombre Campos:</label>
												{nuevo_nombre_campos}
												<p style="font-size:14px;">Escriba y presione enter para agregar los nuevos nombres</p>
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Encriptar Campos del Formulario:</label>
												{encryption}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Área Protegida por Login:</label>
												{area_protegida_por_login}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Activar Envio de Correos Electrónicos:</label>
												{send_email}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Usar Plantilla Formulario HTML:</label>
												{template_fields}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
										<div class="col-md-3 ocultar_campos_requeridos">
											<div class="form-group">
												<label class="form-label">Lista de Campos No Requeridos:</label>
												{campos_no_requeridos}
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarLista">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-3">
											<div class="form-group">
												<label class="form-label">Ocultar Label de campos:</label>
												{ocultar_label}
												<p class="artify_help_block help-block form-text with-errors"></p>
												<a href="javascript:;" class="btn btn-info" id="limpiarLabel">Limpiar Selección</a>
											</div>
										</div>
										<div class="col-md-12 ocultar_editor d-none">
											<div class="row">
												<div class="col-md-2">
													<div id="blocks"></div>
												</div>
												<div class="col-md-10">
													<div id="editor"></div>
												</div>
											</div>

										</div>
										<div class="col-md-12 d-none">
											<div class="form-group">
												<label class="form-label">Campos y Columnas HTML:</label>
												{estructura_de_columnas_y_campos}
												<p class="artify_help_block help-block form-text with-errors"></p>
											</div>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12 d-none">
							<div class="form-group">
								<label class="form-label">Agregar Al Menú Principal:</label>
								{add_menu}
								<p class="artify_help_block help-block form-text with-errors"></p>
							</div>
						</div>
					</div>
					<div class="row mt-3">
						
						<div class="col-md-3">
							<div class="form-group tabla_anidada d-none">
								<label class="form-label">Activar Tabla Anidada:</label>
								{activate_nested_table}
								<p class="artify_help_block help-block form-text with-errors"></p>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="row mt-4">
								<div class="col-md-12 text-center">
									<button type="button" class="btn btn-danger artify-form-control artify-button artify-back regresar_modulos" data-action="back">Regresar</button> 
									<input type="submit" class="btn btn-primary artify-form-control artify-submit" data-action="insert" value="Guardar">
									<!--<a href="javascript:;" class="btn btn-primary siguiente_1">Siguiente <i class="fa fa-arrow-right"></i></a>-->
								</div>
							</div>
						</div>
					</div>
				</div>
			
			</div>
			<div class="tab-pane fade" id="pdf" role="tabpanel" aria-labelledby="pdf-tab">
			
				<div class="row mt-4">
					<div class="col-md-12">
							<div class="form-group">
								<label class="form-label">Activar PDF:</label>
								{activate_pdf}
								<p class="artify_help_block help-block form-text with-errors"></p>
							</div>
						</div>	
					<div class="col-md-6">
						<div class="form-group">
							<label class="form-label">Logo PDF:</label>
							{logo_pdf}
							<p class="artify_help_block help-block form-text with-errors"></p>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label class="form-label">Marca de Agua PDF:</label>
							{marca_de_agua_pdf}
							<p class="artify_help_block help-block form-text with-errors"></p>
						</div>
					</div>
					<div class="col-md-12">
						<div class="form-group">
							<label class="form-label">Consulta de Base de Datos PDF:</label>
							{consulta_pdf}
							<p class="artify_help_block help-block form-text with-errors"></p>
						</div>
					</div>	
				</div>

				<div class="row mt-4">
					<div class="col-md-12 text-center">
						<button type="button" class="btn btn-danger artify-form-control artify-button artify-back regresar_modulos" data-action="back">Regresar</button> 
						<input type="submit" class="btn btn-primary artify-form-control artify-submit" data-action="insert" value="Guardar">
						<a href="javascript:;" class="btn btn-primary anterior"><i class="fa fa-arrow-left"></i> Anterior</a>
						<!--<a href="javascript:;" class="btn btn-primary siguiente_2">Siguiente <i class="fa fa-arrow-right"></i></a>-->
					</div>
				</div>
		
			</div>
		</div>

		</div>
		</div>
		';
		$artify->addPlugin("bootstrap-tag-input");
		$artify->addPlugin("select2");
		$artify->addPlugin("bootstrap-inputmask");
		$artify->addPlugin("summernote");
		$artify->set_template($html_template);
		$artify->fieldCssClass("estructura_de_columnas_y_campos", array("estructura_de_columnas_y_campos"));
		$artify->setLangData("no_data", "No Hay Módulos creados");
		$artify->formFieldValue("active_popup", "No");
		$artify->formFieldValue("add_menu", "Si");
		$artify->formFieldValue("active_filter", "No");
		$artify->formFieldValue("clone_row", "No");
		$artify->formFieldValue("button_add", "Si");
		$artify->formFieldValue("activate_deleteMultipleBtn", "No");
		$artify->formFieldValue("active_search", "No");
		$artify->formFieldValue("encryption", "No");
		$artify->formFieldValue("activar_recaptcha", "No");
		$artify->fieldNotMandatory("actions_buttons_grid");
		$artify->fieldNotMandatory("buttons_actions");
		$artify->fieldNotMandatory("valor_predeterminado_de_campo");
		$artify->formFieldValue("activate_nested_table", "No");
		$artify->formFieldValue("activate_pdf", "No");
		$artify->formFieldValue("refrescar_grilla", "No");
		$artify->formFieldValue("function_filter_and_search", "Si");
		$artify->formFieldValue("activar_union_interna", "No");
		$artify->formFieldValue("activar_union_izquierda", "No");
		$artify->formFieldValue("posicion_botones_accion_grilla", "Derecha");
		$artify->formFieldValue("campos_requeridos", "Si");
		$artify->formFieldValue("mostrar_columna_acciones_grilla", "Si");
		$artify->formFieldValue("mostrar_paginacion", "Si");
		$artify->formFieldValue("activar_numeracion_columnas", "No");
		$artify->formFieldValue("activar_registros_por_pagina", "Si");
		$artify->formFieldValue("cantidad_de_registros_por_pagina", 10);
		$artify->formFieldValue("activar_edicion_en_linea", "No");
		$artify->formFieldValue("posicionarse_en_la_pagina", 1);
		$artify->formFieldValue("ocultar_id_tabla", "No");
		$artify->formFieldValue("totalRecordsInfo", "Si");
		$artify->formFieldValue("area_protegida_por_login", "Si");
		$artify->formfieldValue("posicion_filtro", "Izquierda");
		$artify->formfieldValue("send_email", "No");
		$artify->formfieldValue("activar_graficos", "No");
		$artify->formfieldValue("activar_autosugerencias", "No");

		$artify->setLangData("add", "Agregar Módulo");

		$artify->fieldNotMandatory("posicionarse_en_la_pagina");
		$artify->fieldNotMandatory("nuevo_nombre_campos");
		$artify->fieldNotMandatory("nombre_columnas");
		$artify->fieldNotMandatory("nombre_campos");
		$artify->fieldNotMandatory("nuevo_nombre_columnas");
		$artify->fieldNotMandatory("campos_relacion_union_tabla_principal");
		$artify->fieldNotMandatory("estructura_de_columnas_y_campos");
		$artify->fieldNotMandatory("campos_no_requeridos");
		$artify->fieldNotMandatory("ocultar_label");
		$artify->fieldTypes("logo_pdf", "select");
		$artify->fieldTypes("marca_de_agua_pdf", "select");

		$artify->fieldDataBinding("logo_pdf", "configuracion_modulos", "logo_pdf as configuracion_modulos", "logo_pdf", "db");
		$artify->fieldDataBinding("marca_de_agua_pdf", "configuracion_modulos", "marca_agua_pdf as configuracion_modulos", "marca_agua_pdf", "db");

		$artify->fieldDataAttr("logo_pdf", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("marca_de_agua_pdf", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("consulta_pdf", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("sitekey_recaptcha", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("sitesecret_repatcha", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("mostrar_campos_filtro", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("tabla_principal_union", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("tabla_secundaria_union", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("tabla_principal_union_izquierda", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("tabla_secundaria_union_izquierda", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("posicion_filtro", array("disabled"=>"disabled"));
		$artify->fieldDataAttr("tipo_de_filtro", array("disabled" => "disabled"));
		$artify->fieldDataAttr("campos_relacion_union_tabla_secundaria", array("disabled" => "disabled"));
		$artify->fieldDataAttr("campos_relacion_union_tabla_secundaria_izquierda", array("disabled" => "disabled"));
		$artify->fieldDataAttr("campos_relacion_union_tabla_principal_izquierda", array("disabled" => "disabled"));
				
		$artify->fieldDataAttr("mostrar_campos_busqueda", array("placeholder" => "campo1/campo2/campo3/etc"));
		$artify->fieldDataAttr("mostrar_columnas_grilla", array("placeholder" => "columna1/columna2/columna3/etc"));
		$artify->fieldDataAttr("mostrar_campos_formulario", array("placeholder" => "campo1/campo2/campo3/etc"));
		$artify->fieldDataAttr("mostrar_campos_filtro", array("placeholder" => "campo1/campo2/campo3/etc"));

		$artify->fieldTypes("mostrar_campos_busqueda", "multiselect");
		$artify->fieldTypes("mostrar_campos_formulario", "multiselect");
		$artify->fieldTypes("mostrar_columnas_grilla", "multiselect");
		$artify->fieldTypes("mostrar_campos_filtro", "multiselect");
		$artify->fieldTypes("campos_relacion_union_tabla_principal", "multiselect");
		$artify->fieldTypes("campos_relacion_union_tabla_secundaria", "multiselect");
		$artify->fieldTypes("mostrar_campos_formulario_editar", "multiselect");
		$artify->fieldTypes("nombre_columnas", "multiselect");
		$artify->fieldTypes("nombre_campos", "multiselect");
		$artify->fieldTypes("tabla_principal_union", "multiselect");
		$artify->fieldTypes("tabla_secundaria_union", "multiselect");
		$artify->fieldTypes("ocultar_label", "multiselect");
		$artify->fieldTypes("tabla_principal_union_izquierda", "select");
		$artify->fieldTypes("tabla_secundaria_union_izquierda", "select");
		$artify->fieldTypes("campos_relacion_union_tabla_principal_izquierda", "select");
		$artify->fieldTypes("campos_relacion_union_tabla_secundaria_izquierda", "select");

		$artify->fieldTypes("type_callback", "multiselect");
		$artify->fieldDataBinding("type_callback", array(
			"Antes de Insertar" => "Antes de Insertar", 
			"Despues de Insertar" => "Despues de Insertar",
			"Antes de Actualizar" => "Antes de Actualizar", 
			"Despues de Actualizar" => "Despues de Actualizar",
			"Antes de Eliminar" => "Antes de Eliminar",
			"Despues de Eliminar" => "Despues de Eliminar",
			"Eliminación Masiva" => "Eliminación Masiva",
			"Antes de Actualizar Switch" => "Antes de Actualizar Switch",
			"Despues de Actualizar Switch" => "Despues de Actualizar Switch",
			"Antes de Seleccionar" => "Antes de Seleccionar",
			"Despues de Seleccionar" => "Despues de Seleccionar",
			"Formatear Datos de la Grilla" => "Formatear Datos de la Grilla",
			"Formatear Columnas de la Grilla" => "Formatear Columnas de la Grilla",
			"Formatear Datos Grilla SQL" => "Formatear Datos Grilla SQL",
			"Antes de los Datos de la Grilla (Filtro y/o Busqueda)" => "Antes de los Datos de la Grilla (Filtro y/o Busqueda)",
			"Antes de los Datos de la Grilla SQL (Filtro y/o Busqueda)" => "Antes de los Datos de la Grilla SQL (Filtro y/o Busqueda)"
		), "", "", "array");

		$artify->fieldTypes("nombre_grafico", "input");
		$artify->fieldTypes("tipo_de_filtro", "input");
		$artify->fieldTypes("valor_predeterminado_de_campo", "input");
		$artify->fieldTypes("ordenar_grilla_por", "select");
		$artify->fieldTypes("nuevo_nombre_columnas", "input");
		$artify->fieldTypes("campos_no_requeridos", "multiselect");

		$artify->fieldTypes("nuevo_nombre_campos", "input");
		$artify->fieldAttributes("nuevo_nombre_columnas", array("data-role"=>"tagsinput"));
		$artify->fieldAttributes("nuevo_nombre_campos", array("data-role"=>"tagsinput"));

		$artify->fieldTypes("activar_autosugerencias", "select");
		$artify->fieldDataBinding("activar_autosugerencias", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("area_protegida_por_login", "select");
		$artify->fieldDataBinding("area_protegida_por_login", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("send_email", "select");
		$artify->fieldDataBinding("send_email", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("tipo_orden", "select");
		$artify->fieldDataBinding("tipo_orden", array("ASC" => "ASC", "DESC" => "DESC"), "", "", "array");
		
		$artify->fieldTypes("posicion_botones_accion_grilla", "select");
		$artify->fieldDataBinding("posicion_botones_accion_grilla", array("Derecha" => "Derecha", "Izquierda" => "Izquierda"), "", "", "array");
		
		$artify->fieldTypes("activar_edicion_en_linea", "select");
		$artify->fieldDataBinding("activar_edicion_en_linea", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activar_registros_por_pagina", "select");
		$artify->fieldDataBinding("activar_registros_por_pagina", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activar_numeracion_columnas", "select");
		$artify->fieldDataBinding("activar_numeracion_columnas", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("mostrar_columna_acciones_grilla", "select");
		$artify->fieldDataBinding("mostrar_columna_acciones_grilla", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("mostrar_paginacion", "select");
		$artify->fieldDataBinding("mostrar_paginacion", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("campos_requeridos", "select");
		$artify->fieldDataBinding("campos_requeridos", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("encryption", "select");
		$artify->fieldDataBinding("encryption", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("refrescar_grilla", "select");
		$artify->fieldDataBinding("refrescar_grilla", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("buttons_actions", "checkbox");
		$artify->fieldDataBinding("buttons_actions", array(
			"Ver" => "Mostrar botón Ver",
			"Editar" => "Mostrar botón Editar",
			"Eliminar" => "Mostrar botón Eliminar",
			"Guardar" => "Ocultar botón Guardar",
			"Guardar y regresar" => "Ocultar botón Guardar y regresar",
			"Regresar" => "Ocultar botón Regresar", 
			"Cancelar" => "Ocultar botón Cancelar", 
			"Personalizado PDF" => "Mostrar botón Personalizado PDF"
		), "", "", "array");

		$artify->fieldTypes("actions_buttons_grid", "checkbox");
		$artify->fieldDataBinding("actions_buttons_grid", array(
			"Imprimir" => "Imprimir", 
			"PDF" => "PDF", 
			"CSV" => "CSV", 
			"Excel" => "Excel"
		), "", "", "array");

		$artify->fieldTypes("posicion_filtro", "select");
		$artify->fieldDataBinding("posicion_filtro", array("Izquierda" => "Izquierda", "Derecha" => "Derecha", "Arriba" => "Arriba"), "", "", "array");

		$artify->fieldTypes("totalRecordsInfo", "select");
		$artify->fieldDataBinding("totalRecordsInfo", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("ocultar_id_tabla", "select");
		$artify->fieldDataBinding("ocultar_id_tabla", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activar_union_interna", "select");
		$artify->fieldDataBinding("activar_union_interna", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activar_union_izquierda", "select");
		$artify->fieldDataBinding("activar_union_izquierda", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activate_nested_table", "select");
		$artify->fieldDataBinding("activate_nested_table", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activar_recaptcha", "select");
		$artify->fieldDataBinding("activar_recaptcha", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("button_add", "select");
		$artify->fieldDataBinding("button_add", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activate_pdf", "select");
		$artify->fieldDataBinding("activate_pdf", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("activate_deleteMultipleBtn", "select");
		$artify->fieldDataBinding("activate_deleteMultipleBtn", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("active_search", "select");
		$artify->fieldDataBinding("active_search", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("active_popup", "select");
		$artify->fieldDataBinding("active_popup", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("active_filter", "select");
		$artify->fieldDataBinding("active_filter", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("function_filter_and_search", "select");
		$artify->fieldDataBinding("function_filter_and_search", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("clone_row", "select");
		$artify->fieldDataBinding("clone_row", array("Si" => "Si", "No" => "No"), "", "", "array");

		$artify->fieldTypes("tabla", "select");
		
		$artify->fieldCssClass("crud_type", array("crud_type"));
		$artify->fieldCssClass("tabla", array("tabla"));
		$artify->fieldCssClass("id_tabla", array("id_tabla"));
		$artify->fieldCssClass("query", array("query"));
		$artify->fieldCssClass("name_view", array("name_view"));
		$artify->fieldCssClass("controller_name", array("controller_name"));
		$artify->fieldCssClass("columns_table", array("columns_table"));
		$artify->fieldCssClass("activate_nested_table", array("activate_nested_table"));
		$artify->fieldCssClass("consulta_pdf", array("consulta_pdf"));
		$artify->fieldCssClass("activate_pdf", array("activate_pdf"));
		$artify->fieldCssClass("logo_pdf", array("logo_pdf"));
		$artify->fieldCssClass("marca_de_agua_pdf", array("marca_de_agua_pdf"));
		$artify->fieldCssClass("actions_buttons_grid", array("actions_buttons_grid"));
		$artify->fieldCssClass("buttons_actions", array("buttons_actions"));
		$artify->fieldCssClass("mostrar_campos_busqueda", array("mostrar_campos_busqueda"));
		$artify->fieldCssClass("mostrar_columnas_grilla", array("mostrar_columnas_grilla"));
		$artify->fieldCssClass("mostrar_campos_filtro", array("mostrar_campos_filtro"));
		$artify->fieldCssClass("mostrar_campos_formulario", array("mostrar_campos_formulario"));
		$artify->fieldCssClass("activar_recaptcha", array("activar_recaptcha"));
		$artify->fieldCssClass("sitekey_recaptcha", array("sitekey_recaptcha"));
		$artify->fieldCssClass("sitesecret_repatcha", array("sitesecret_repatcha"));
		$artify->fieldCssClass("active_filter", array("active_filter"));
		$artify->fieldCssClass("function_filter_and_search", array("function_filter_and_search"));
		$artify->fieldCssClass("mostrar_campos_formulario_editar", array("mostrar_campos_formulario_editar"));
		$artify->fieldCssClass("ordenar_grilla_por", array("ordenar_grilla_por"));
		$artify->fieldCssClass("mostrar_columna_acciones_grilla", array("mostrar_columna_acciones_grilla"));
		$artify->fieldCssClass("posicion_botones_accion_grilla", array("posicion_botones_accion_grilla"));
		$artify->fieldCssClass("refrescar_grilla", array("refrescar_grilla"));
		$artify->fieldCssClass("clone_row", array("clone_row"));
		$artify->fieldCssClass("activar_numeracion_columnas", array("activar_numeracion_columnas"));
		$artify->fieldCssClass("mostrar_paginacion", array("mostrar_paginacion"));
		$artify->fieldCssClass("cantidad_de_registros_por_pagina", array("cantidad_de_registros_por_pagina"));
		$artify->fieldCssClass("activar_registros_por_pagina", array("activar_registros_por_pagina"));
		$artify->fieldCssClass("posicionarse_en_la_pagina", array("posicionarse_en_la_pagina"));
		$artify->fieldCssClass("activar_edicion_en_linea", array("activar_edicion_en_linea"));
		$artify->fieldCssClass("activate_deleteMultipleBtn", array("activate_deleteMultipleBtn"));
		$artify->fieldCssClass("active_popup", array("active_popup"));
		$artify->fieldCssClass("active_search", array("active_search"));
		$artify->fieldCssClass("button_add", array("button_add"));
		$artify->fieldCssClass("tipo_orden", array("tipo_orden"));
		$artify->fieldCssClass("nombre_columnas", array("nombre_columnas"));
		$artify->fieldCssClass("nuevo_nombre_columnas", array("tagsinput"));
		$artify->fieldCssClass("nombre_campos", array("nombre_campos"));
		$artify->fieldCssClass("nuevo_nombre_campos", array("tagsinput"));
		$artify->fieldCssClass("ocultar_id_tabla", array("ocultar_id_tabla"));
		$artify->fieldCssClass("tipo_de_filtro", array("tipo_de_filtro"));
		$artify->fieldCssClass("campos_relacion_union_tabla_principal", array("campos_relacion_union_tabla_principal"));
		$artify->fieldCssClass("campos_relacion_union_tabla_secundaria", array("campos_relacion_union_tabla_secundaria"));
		$artify->fieldCssClass("campos_relacion_union_tabla_principal_izquierda", array("campos_relacion_union_tabla_principal_izquierda"));
		$artify->fieldCssClass("campos_relacion_union_tabla_secundaria_izquierda", array("campos_relacion_union_tabla_secundaria_izquierda"));
		$artify->fieldCssClass("template_fields", array("template_fields"));
		$artify->fieldCssClass("tabla_principal_union", array("tabla_principal_union"));
		$artify->fieldCssClass("tabla_secundaria_union", array("tabla_secundaria_union"));
		$artify->fieldCssClass("tabla_principal_union_izquierda", array("tabla_principal_union_izquierda"));
		$artify->fieldCssClass("tabla_secundaria_union_izquierda", array("tabla_secundaria_union_izquierda"));
		$artify->fieldCssClass("activar_union_interna", array("activar_union_interna"));
		$artify->fieldCssClass("activar_union_izquierda", array("activar_union_izquierda"));
		$artify->fieldCssClass("nombre_modulo", array("nombre_modulo"));
		$artify->fieldCssClass("posicion_filtro", array("posicion_filtro"));
		$artify->fieldCssClass("type_callback", array("type_callback"));
		$artify->fieldCssClass("type_fields", array("type_fields"));
		$artify->fieldCssClass("send_email", array("send_email"));
		$artify->fieldCssClass("text_no_data", array("text_no_data"));
		$artify->fieldCssClass("nuevo_nombre_columnas", array("nuevo_nombre_columnas"));
		$artify->fieldCssClass("totalRecordsInfo", array("totalRecordsInfo"));
		$artify->fieldCssClass("campos_no_requeridos", array("campos_no_requeridos"));
		$artify->fieldCssClass("campos_requeridos", array("campos_requeridos"));
		$artify->fieldCssClass("ocultar_label", array("ocultar_label"));
		$artify->fieldCssClass("valor_predeterminado_de_campo", array("valor_predeterminado_de_campo"));
		$artify->fieldCssClass("file_callback", array("file_callback"));

		$artify->fieldAttributes("id_tabla", array("readonly" => "true"));
		$artify->fieldAttributes("consulta_pdf", array("placeholder"=> "Ejemplo: SELECT id as item FROM tabla", "style"=> "min-height: 200px; max-height: 200px;"));
		$artify->fieldAttributes("consulta_crear_tabla", array("placeholder"=> "Rellena los campos de abajo para completar estos valores o ingresalos manualmente. Ejemplo: id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255)", "style"=> "min-height: 200px; max-height: 200px;"));
		$artify->fieldAttributes("query", array("placeholder"=> "Ejemplo: SELECT id as ID, name as Name FROM demo", "style"=> "min-height: 200px; max-height: 200px;"));
		$artify->fieldAttributes("columns_table", array("placeholder"=> "Rellena los campos de abajo para completar estos valores o ingresalos manualmente. Ejemplo: id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255)", "style"=> "min-height: 200px; max-height: 200px;"));
		$artify->fieldGroups("Name2",array("name_view","add_menu"));
		$artify->tableHeading("Generador de Módulos");
		
		$artify->setSearchCols(array(
			"crud_type",
			"tabla",
			"id_tabla", 
			"controller_name", 
			"name_view", 
			"add_menu", 
			"active_filter",
			"clone_row", 
			"active_popup",
			"active_search", 
			"activate_deleteMultipleBtn", 
			"button_add", 
			"actions_buttons_grid", 
			"activate_nested_table", 
			"buttons_actions"
		));
		$artify->formFields(array(
			"activar_autosugerencias",
			"valor_predeterminado_de_campo",
			"ocultar_label",
			"campos_no_requeridos",
			"estructura_de_columnas_y_campos",
			"activar_union_izquierda",
			"tabla_principal_union_izquierda",
			"campos_relacion_union_tabla_principal_izquierda",
			"tabla_secundaria_union_izquierda",
			"campos_relacion_union_tabla_secundaria_izquierda",
			"send_email",
			"text_no_data",
			"type_fields",
			"type_callback", 
			"file_callback", 
			"posicion_filtro", 
			"campos_relacion_union_tabla_principal", 
			"campos_relacion_union_tabla_secundaria", 
			"tabla_principal_union", 
			"tabla_secundaria_union", 
			"area_protegida_por_login", 
			"totalRecordsInfo", 
			"activate_nested_table", 
			"tipo_de_filtro", 
			"ocultar_id_tabla", 
			"nombre_campos", 
			"nuevo_nombre_campos", 
			"nombre_columnas", 
			"nuevo_nombre_columnas", 
			"posicionarse_en_la_pagina", 
			"ordenar_grilla_por", 
			"tipo_orden", 
			"nombre_modulo", 
			"activar_edicion_en_linea", 
			"cantidad_de_registros_por_pagina", 
			"activar_numeracion_columnas", 
			"activar_registros_por_pagina", 
			"mostrar_paginacion", 
			"mostrar_columna_acciones_grilla", 
			"campos_requeridos", 
			"posicion_botones_accion_grilla", 
			"mostrar_campos_formulario_editar", 
			"activar_union_interna", 
			"function_filter_and_search", 
			"activar_recaptcha", 
			"sitekey_recaptcha", 
			"sitesecret_repatcha", 
			"query_get", 
			"encryption", 
			"mostrar_columnas_grilla", 
			"mostrar_campos_filtro", 
			"mostrar_campos_formulario", 
			"mostrar_campos_busqueda", 
			"consulta_pdf", 
			"refrescar_grilla", 
			"logo_pdf", 
			"marca_de_agua_pdf", 
			"activate_pdf", 
			"tabla", 
			"id_tabla",
			"crud_type", 
			"query", 
			"controller_name", 
			"name_view", 
			"add_menu", 
			"template_fields", 
			"active_filter", 
			"clone_row", 
			"active_popup", 
			"active_search", 
			"activate_deleteMultipleBtn", 
			"button_add", 
			"actions_buttons_grid", 
			"buttons_actions"
		));
		$artify->editFormFields(array(
			"activar_autosugerencias",
			"valor_predeterminado_de_campo",
			"ocultar_label",
			"campos_no_requeridos",
			"estructura_de_columnas_y_campos",
			"activar_union_izquierda",
			"tabla_principal_union_izquierda",
			"campos_relacion_union_tabla_principal_izquierda",
			"tabla_secundaria_union_izquierda",
			"campos_relacion_union_tabla_secundaria_izquierda",
			"send_email",
			"text_no_data",
			"type_fields",
			"type_callback", 
			"file_callback", 
			"posicion_filtro", 
			"campos_relacion_union_tabla_principal", 
			"campos_relacion_union_tabla_secundaria", 
			"tabla_principal_union", 
			"tabla_secundaria_union", 
			"area_protegida_por_login", 
			"totalRecordsInfo", 
			"tipo_de_filtro", 
			"ocultar_id_tabla", 
			"nombre_campos", 
			"nuevo_nombre_campos", 
			"nombre_columnas", 
			"nuevo_nombre_columnas", 
			"posicionarse_en_la_pagina", 
			"ordenar_grilla_por", 
			"tipo_orden", 
			"nombre_modulo", 
			"activar_edicion_en_linea", 
			"cantidad_de_registros_por_pagina", 
			"activar_numeracion_columnas", 
			"activar_registros_por_pagina", 
			"mostrar_paginacion", 
			"mostrar_columna_acciones_grilla", 
			"campos_requeridos", 
			"posicion_botones_accion_grilla", 
			"mostrar_campos_formulario_editar", 
			"activar_union_interna", 
			"function_filter_and_search", 
			"activar_recaptcha", 
			"sitekey_recaptcha", 
			"sitesecret_repatcha", 
			"query_get", 
			"encryption", 
			"mostrar_columnas_grilla", 
			"mostrar_campos_filtro", 
			"mostrar_campos_formulario", 
			"mostrar_campos_busqueda", 
			"consulta_pdf", 
			"refrescar_grilla", 
			"logo_pdf", 
			"marca_de_agua_pdf", 
			"activate_pdf", 
			"tabla", 
			"id_tabla", 
			"crud_type", 
			"query", 
			"controller_name", 
			"name_view", 
			"add_menu", 
			"template_fields", 
			"active_filter", 
			"clone_row", 
			"active_popup", 
			"active_search", 
			"activate_deleteMultipleBtn", 
			"button_add", 
			"actions_buttons_grid",
			"activate_nested_table", 
			"buttons_actions"
		));

		$artify->crudTableCol(array(
			"crud_type",
			"tabla",
			"nombre_modulo",
			"id_tabla", 
			"controller_name", 
			"name_view", 
			"add_menu", 
			"active_filter", 
			"clone_row", 
			"active_popup", 
			"active_search", 
			"activate_deleteMultipleBtn", 
			"button_add", 
			"actions_buttons_grid", 
			"activate_nested_table", 
			"buttons_actions"
		));
		$artify->colRename("tabla", "Nombre Tabla Base de Datos");
		$artify->colRename("id_tabla", "ID Tabla Base de Datos");
		$artify->colRename("crud_type", "Tipo de Módulo");
		$artify->colRename("active_popup", "Activar Popup");
		$artify->colRename("active_search", "Activar Búsqueda");
		$artify->colRename("activate_deleteMultipleBtn", "Activar Eliminación Masiva");
		$artify->colRename("button_add", "Botón Agregar");
		$artify->colRename("actions_buttons_grid", "Botones de Exportación Grilla");
		$artify->colRename("activate_nested_table", "Activar Tabla Anidada");
		$artify->colRename("id_modulos", "ID");
		$artify->colRename("buttons_actions", "Botones de Acción");
		$artify->colRename("mostrar_campos_busqueda", "Campos a Mostrar en la Busqueda");

		$artify->colRename("active_filter", "Activar Filtro de Busqueda");
		$artify->colRename("clone_row", "Clonar Fila");
		
		$artify->colRename("template_fields", "Usar Plantilla Formulario HTML");

		$artify->fieldConditionalLogic("crud_type", "CRUD", "=", "query", "hide");
		$artify->fieldConditionalLogic("crud_type", "CRUD", "!=", "query", "show");

		$artify->fieldConditionalLogic("crud_type", "Formulario de edición", "=", "query", "hide");
		$artify->fieldConditionalLogic("crud_type", "Formulario de inserción", "=", "query", "hide");
		
		$artify->formFieldValue("template_fields", "No");

		$artify->colRename("query", "Consulta BD");
		$artify->colRename("controller_name", "Nombre del Controlador");
		$artify->colRename("columns_table", "Columnas de la Tabla");
		$artify->colRename("name_view", "Nombre de la Vista");
		$artify->colRename("add_menu", "Agregar Al Menú Principal");
		$artify->fieldDesc("nombre_funcion_antes_de_insertar", "Campo opcional");

		$artify->fieldTypes("crud_type", "select");
		$artify->fieldDataBinding("crud_type", array(
			"CRUD"=> "CRUD (Mantenedor a base de una tabla)",
			"SQL"=> "SQL (Mantenedor a base de una Tabla y una consulta)",
			"Formulario de inserción" => "Formulario de inserción",
			"Formulario de edición" => "Formulario de edición",
			"Formulario de Una Página Más Grilla" => "Formulario de Una Página Más Grilla"
		), "", "","array");

		$artify->fieldTypes("add_menu", "select");
		$artify->fieldDataBinding("add_menu", array("Si"=> "Si"), "", "","array");

		$artify->fieldTypes("template_fields", "select");
		$artify->fieldDataBinding("template_fields", array("Si"=> "Si", "No"=> "No"), "", "","array");

		$artify->buttonHide("submitBtnSaveBack");
		$artify->setSettings("template", "modulos");
		$artify->setSettings("searchbox", true);
		$artify->setSettings("viewbtn", false);
		$artify->setSettings("refresh", false);
		$artify->setSettings("printBtn", false);
		$artify->setSettings("editbtn", true);
		$artify->setSettings("delbtn", true);
		$artify->setSettings("pdfBtn", false);
		$artify->setSettings("csvBtn", false);
		$artify->setSettings("excelBtn", false);
		$artify->setSettings("preventXSS", false);
		$artify->setSettings("function_filter_and_search", true);
		$artify->addCallback("before_insert", "insertar_modulos", array($id_sesion_usuario));
		$artify->addCallback("after_insert", "despues_de_insertar_modulos");
		$artify->addCallback("before_update", "actualizar_modulos");
		$artify->addCallback("before_delete", "eliminar_modulos");

		$artify->buttonHide("submitBtn");
		$artify->buttonHide("submitBtnBack");
		$artify->buttonHide("cancel");

		$action = $_ENV["BASE_URL"] . "{controller_name}";
		$text = '<i class="fa fa-table" aria-hidden="true"></i>';
		$attr = array("title" => "Ver módulo", "target"=> "_blank");
		$artify->enqueueBtnActions("url btn btn-default btn-sm ", $action, "url", $text, "", $attr);
		//$artify->formFieldValue("query_get", "http://localhost/artify/nombre_controlador_api/nombre_metodo_api");
		$render = $artify->dbTable("modulos")->render();
		$switch = $artify->loadPluginJsCode("bootstrap-switch-master",".actions_buttons_grid, .buttons_actions, .actions_buttons_grid_db, .buttons_actions_db");
		$tags = $artify->loadPluginJsCode("bootstrap-tag-input",".tagsinput");

		$pdf = DB::ArtifyCrud(true);
		$pdf->tableHeading("Configuraciones de PDF");
		$pdf->setLangData("add", "Agregar PDF");
		$pdf->setSettings("searchbox", true);
		$pdf->setSettings("editbtn", true);
		$pdf->setSettings("delbtn", true);
		$pdf->setSettings("function_filter_and_search", true);
		$pdf->setLangData("no_data", "No se encontrarón Configuraciones");
		$pdf->colRename("id_configuraciones_pdf", "ID");
		$pdf->fieldTypes("logo_pdf", "FILE_NEW");
		$pdf->fieldTypes("marca_agua_pdf", "FILE_NEW");
		$pdf->buttonHide("submitBtnSaveBack");
		$pdf->fieldNotMandatory("logo_pdf");
		$pdf->fieldNotMandatory("marca_agua_pdf");
		$pdf->fieldGroups("group1",array("logo_pdf","marca_agua_pdf"));
		$render_pdf = $pdf->dbTable("configuraciones_pdf")->render();

		View::render("modulos", 
			[
				'render' => $render, 
				'tags' => $tags,
				'switch' => $switch, 
				'render_tablas' => $render_tablas, 
				'render_pdf' => $render_pdf
			]
		);
	}

	public static function configuracion(){
		$artify = DB::ArtifyCrud();
		$queryfy = $artify->getQueryfyObj();
		$data = $queryfy->select("configuracion");
		return $data;
	}

	public function obtener_campos_union_izquierda(){
		$request = new Request();
	
		if ($request->getMethod() === 'POST') {
			$tabla_principal_union_izquierda = $request->post('tabla_principal_union_izquierda');
			$campos_relacion_union_tabla_principal_izquierda = $request->post('campos_relacion_union_tabla_principal_izquierda');
			$tabla_secundaria_union_izquierda = $request->post('tabla_secundaria_union_izquierda');
			$campos_relacion_union_tabla_secundaria_izquierda = $request->post('campos_relacion_union_tabla_secundaria_izquierda');

			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$tabla1 = $queryfy->columnNames($tabla_principal_union_izquierda);
			$tabla2 = $queryfy->columnNames($tabla_secundaria_union_izquierda);

			echo json_encode(["tabla1" => $tabla1, 'tabla2' => $tabla2]);
		}
	}

	private function limpiarTexto($texto) {
        // Reemplazar espacios con guiones bajos
        $texto = str_replace(' ', '_', $texto);
    
        // Eliminar acentos
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ]);
        
        // Eliminar cualquier carácter no alfanumérico (opcional)
        $texto = preg_replace('/[^A-Za-z0-9_]/', '', $texto);
        
        return $texto;
    }

	public function obtener_campos_relacion_union_interna() {
		$request = new Request();
	
		if ($request->getMethod() === 'POST') {
			$lastSelected = $request->post('lastSelected');
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$data = $queryfy->columnNames($lastSelected);
	
			// Verificar si $data es un array antes de filtrar
			if (is_array($data)) {
				$filteredData = array_filter($data, function($column) {
					return strpos($column, 'id_') === 0;
				});
			} else {
				$filteredData = []; // Si $data no es un array, establecer un array vacío
			}
	
			echo json_encode(["data" => $filteredData]);
		}
	}	

	public function obtener_columnas_tabla(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$tabla = $request->post('tabla');
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$columnNames = $queryfy->columnNames($tabla);

			echo json_encode(["columnas_tablas" => $columnNames]);
		}
	}

	public function obtener_id_tabla(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$tabla = $request->post('val');
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$primaryKey = $queryfy->primaryKey($tabla);
			$columnNames = $queryfy->columnNames($tabla);
			$tablas_all = $queryfy->select("crear_tablas");

			echo json_encode(["columnas_tablas" => $columnNames, "id_tablas" => $primaryKey, 'tablas' => $tablas_all]);
		}
	}

	public function obtener_tabla_id(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$dataId = $request->post('dataId');
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();

			$queryfy->where("id_modulos", $dataId);
			$modulos = $queryfy->select("modulos");

			echo json_encode(["modulos" => $modulos]);
		}
	}

	public function obtener_tablas(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$result = $queryfy->select("crear_tablas");

			echo json_encode(["tablas" => $result]);
		}
	}

	public function generarToken(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			try {
				$data = array("data" => array("usuario" => "admin", "password" => "123"));
				$data = json_encode($data);
			
				$client = new Client();
				$response = $client->post("http://localhost/". $_ENV["BASE_URL"]."/api/usuario/?op=jwtauth", [
					'body' => $data
				]);
	
				$result = $response->getBody()->getContents();
				echo $result;
	
			} catch (ClientException $e) {
				if ($e->getResponse()->getStatusCode() == 404) {
					echo $e->getResponse()->getBody()->getContents() . PHP_EOL;
				}
			}
		}
	}

	/*public function obtenerTablaActual(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$tabla = $request->post('tabla');

			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$columnDB = $queryfy->tableFieldInfo($tabla);

			echo json_encode(['columnas_tabla' => $columnDB]);
		}
	}*/

	public function actualizar_orden_menu(){

		$request = new Request();

		if ($request->getMethod() === 'POST') {

			$order = $request->post('order');
			if (isset($order) && is_array($order)) {
				$newOrder = $order;

				foreach ($newOrder as $position => $itemId) {
					$position++;
					$artify = DB::ArtifyCrud();
					$queryfy = $artify->getQueryfyObj();
					$queryfy->where("id_menu", $itemId);
					$queryfy->update("menu", array("orden_menu" => $position));
				}

				echo json_encode(['success' => 'Orden del menu actualizado correctamente']);
			}
		}
	}

	public function actualizar_orden_submenu(){

		$request = new Request();

		if ($request->getMethod() === 'POST') {

			$order = $request->post('order');
			if (isset($order) && is_array($order)) {
				$newOrder = $order;

				foreach ($newOrder as $position => $itemId) {
					$position++;
					$artify = DB::ArtifyCrud();
					$queryfy = $artify->getQueryfyObj();
					$queryfy->where("id_submenu", $itemId);
					$queryfy->update("submenu", array("orden_submenu" => $position));
				}

				echo json_encode(['success' => 'Orden del submenu actualizado correctamente']);
			}
		}
	}

	public function editar_iconos_menu(){

		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$id = $request->post('id');

			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$queryfy->columns = array("icono_menu");
			$queryfy->where("id_menu", $id);
			$data = $queryfy->select("menu");

			$ruta_json = "http://" . $_SERVER['HTTP_HOST'] .$_ENV["BASE_URL"] . "js/icons.json";

			// Lee el contenido del archivo JSON
			$contenido_json = file_get_contents($ruta_json);

			// Decodifica el contenido JSON a un array de PHP
			$icons = json_decode($contenido_json, true);

        	echo json_encode(['data' => $data, 'icons' => $icons], JSON_UNESCAPED_UNICODE);
		}
	}

	public function editar_iconos_submenu(){

		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$id = $request->post('id');

			$artify = DB::ArtifyCrud();
			$queryfy = $artify->getQueryfyObj();
			$queryfy->columns = array("icono_submenu");
			$queryfy->where("id_submenu", $id);
			$data = $queryfy->select("submenu");

			$ruta_json = "http://" . $_SERVER['HTTP_HOST'] .$_ENV["BASE_URL"] . "js/icons.json";

			// Lee el contenido del archivo JSON
			$contenido_json = file_get_contents($ruta_json);

			// Decodifica el contenido JSON a un array de PHP
			$icons = json_decode($contenido_json, true);

        	echo json_encode(['data' => $data, 'icons' => $icons], JSON_UNESCAPED_UNICODE);
		}
	}

	public function menu(){
		$artify = DB::ArtifyCrud();

		$queryfy = $artify->getQueryfyObj();
		$datamenu = $queryfy->DBQuery("SELECT MAX(orden_menu) as orden FROM menu");
		$newOrdenMenu = $datamenu[0]["orden"] + 1;

		$datasubmenu = $queryfy->DBQuery("SELECT MAX(orden_submenu) as orden_submenu FROM submenu");
		$newOrdenSubMenu = $datasubmenu[0]["orden_submenu"] + 1;

		$artify->addWhereConditionActionButtons("delete", "id_menu", "!=", array(4,5,6,7,10,12,19, 141));
		$artify->addWhereConditionActionButtons("edit", "id_menu", "!=", array(4,5,6,7,10,12,19, 141));

		$action = "javascript:;";
		$text = '<i class="fas fa-arrows-alt-v"></i>';
		$attr = array("title"=>"Arrastra para Reordenar Fila");
		$artify->enqueueBtnActions("url btn btn-primary btn-sm reordenar_fila", $action, "url",$text,"orden_menu", $attr);
		$artify->multiTableRelationDisplay("tab", "Menu");
		$artify->bulkCrudUpdate("nombre_menu", "text",array("data-some-attr" =>"some-dummy-val"));
		$artify->bulkCrudUpdate("url_menu", "text",array("data-some-attr" =>"some-dummy-val"));
		$artify->bulkCrudUpdate("area_protegida_menu", "select", array("data-cust-attr" =>"some-cust-val"),
		array(
			array(
				"Si",
				"Si"
			),
			array(
				"No",
				"No"
			),
		));
		$artify->setSearchCols(array("nombre_menu","url_menu", "icono_menu", "submenu", "orden_menu", "area_protegida_menu"));
		$artify->fieldHideLable("orden_menu");
		$artify->fieldDataAttr("orden_menu", array("style"=>"display:none"));
		$artify->fieldHideLable("submenu");
		$artify->fieldDataAttr("submenu", array("style"=>"display:none"));
		$artify->formFieldValue("orden_menu", $newOrdenMenu);
		$artify->formFieldValue("submenu", "No");
		$artify->addPlugin("select2");
		$artify->dbOrderBy("orden_menu asc");
		$artify->addCallback("format_table_data", "formatTableMenu");
		$artify->addCallback("after_insert", "agregar_menu");
		$artify->addCallback("before_delete", "eliminar_menu");
		$artify->fieldTypes("icono_menu", "select");
		$artify->fieldCssClass("icono_menu", array("icono_menu"));
		$artify->fieldCssClass("submenu", array("submenu"));
		$artify->fieldGroups("group1", array("nombre_menu", "url_menu"));
		$artify->fieldGroups("group2", array("icono_menu", "area_protegida_menu"));
		$artify->fieldDesc("area_protegida_menu", "Seleccione si este menu estará protegido por un login de acceso a usuarios o no");
		$artify->crudRemoveCol(array("id_menu"));
		$artify->setSettings("searchbox", true);
		$artify->setSettings("printBtn", false);
		$artify->setSettings("pdfBtn", false);
		$artify->setSettings("csvBtn", false);
		$artify->setSettings("excelBtn", false);
		$artify->setSettings("viewbtn", false);
		$artify->setSettings("refresh", false);
		$artify->setSettings('editbtn', true);    
		$artify->setSettings('delbtn', true);
		$artify->setSettings("function_filter_and_search", true);
		$artify->buttonHide("submitBtnSaveBack");
		$artify->fieldTypes("area_protegida_menu", "select");
		$artify->fieldDataBinding("area_protegida_menu", array("Si" => "Si", "No" => "No"), "", "","array");

		$submenu = DB::ArtifyCrud(true);
		$submenu->multiTableRelationDisplay("tab", "SubMenu");
		$action = "javascript:;";
		$text = '<i class="fas fa-arrows-alt-v"></i>';
		$attr = array("title"=>"Arrastra para Reordenar Fila");
		$submenu->enqueueBtnActions("url btn btn-primary btn-sm reordenar_fila_submenu", $action, "url", $text, "orden_submenu", $attr);
		$submenu->fieldHideLable("orden_submenu");
		$submenu->fieldDataAttr("orden_submenu", array("style"=>"display:none"));
		$submenu->fieldHideLable("id_menu");
		$submenu->fieldDataAttr("id_menu", array("style"=>"display:none"));
		$submenu->setSearchCols(array("nombre_submenu","url_submenu", "icono_submenu", "orden_submenu"));
		$submenu->crudTableCol(array("nombre_submenu","url_submenu", "icono_submenu", "orden_submenu"));
		$submenu->formFields(array("id_menu","nombre_submenu","url_submenu", "icono_submenu", "orden_submenu"));
		$submenu->dbTable("submenu");
		$submenu->dbOrderBy("orden_submenu asc");
		$submenu->addCallback("format_table_data", "formatTableSubMenu");
		$submenu->addCallback("before_insert", "insertar_submenu");
		$submenu->addCallback("after_insert", "despues_insertar_submenu");
		$submenu->addCallback("before_update", "modificar_submenu");
		$submenu->addCallback("before_delete", "eliminar_submenu");
		$submenu->fieldGroups("Name", array("nombre_submenu", "url_submenu"));
		$submenu->formFieldValue("orden_submenu", $newOrdenSubMenu);
		$submenu->setSettings("template", "submenu");
		$submenu->setSettings("searchbox", true);
		$submenu->setSettings("printBtn", false);
		$submenu->setSettings("pdfBtn", false);
		$submenu->setSettings('editbtn', true);    
		$submenu->setSettings('delbtn', true);
		$submenu->setSettings("csvBtn", false);
		$submenu->setSettings("excelBtn", false);
		$submenu->setSettings("viewbtn", false);
		$submenu->setSettings("function_filter_and_search", true);
		$submenu->fieldTypes("icono_submenu", "select");
		$submenu->fieldCssClass("icono_submenu", array("icono_submenu"));
		$submenu->buttonHide("submitBtnSaveBack");
		$artify->multiTableRelation("id_menu", "id_menu", $submenu);
		$select2 = $artify->loadPluginJsCode("select2",".icono_menu, .icono_submenu");
		$render = $artify->dbTable("menu")->render();

		View::render(
			"menu", [
				'render' => $render,
				'select2' => $select2
			]
		);
	}

	public function cargar_vista_submenu(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$modulosubmenu = DB::ArtifyCrud(true);

			$queryfy = $modulosubmenu->getQueryfyObj();
			$datasubmenu = $queryfy->DBQuery("SELECT MAX(orden_submenu) as orden_submenu FROM submenu");
			$newOrdenSubMenu = $datasubmenu[0]["orden_submenu"] + 1;

			$action = "javascript:;";
			$text = '<i class="fas fa-arrows-alt-v"></i>';
			$attr = array("title"=>"Arrastra para Reordenar Fila");
			$modulosubmenu->enqueueBtnActions("url btn btn-primary btn-sm reordenar_fila_submenu", $action, "url", $text, "orden_submenu", $attr);
			$modulosubmenu->addPlugin("select2");
			$modulosubmenu->setSettings("template", "submenu");
			$modulosubmenu->fieldHideLable("orden_submenu");
			$modulosubmenu->fieldDataAttr("orden_submenu", array("style"=>"display:none"));
			$modulosubmenu->formFieldValue("orden_submenu", $newOrdenSubMenu);
			$modulosubmenu->fieldTypes("icono_submenu", "select");
			$modulosubmenu->fieldCssClass("icono_submenu", array("icono_menu"));
			$modulosubmenu->fieldGroups("group1", array("nombre_submenu", "url_submenu", "icono_submenu"));
			$modulosubmenu->fieldRenameLable("id_menu", "Submenu Asignado a Menu");
			$modulosubmenu->colRename("id_menu", "Submenu Asignado a Menu");
			$modulosubmenu->setSettings("searchbox", true);
			$modulosubmenu->setSettings("printBtn", false);
			$modulosubmenu->setSettings("pdfBtn", false);
			$modulosubmenu->setSettings("csvBtn", false);
			$modulosubmenu->setSettings("excelBtn", false);
			$modulosubmenu->setSettings("viewbtn", false);
			$modulosubmenu->setSettings('editbtn', true);
			$modulosubmenu->setSettings('delbtn', true);
			$modulosubmenu->setSettings("function_filter_and_search", true);

			$sql = "SELECT id_menu, nombre_menu FROM menu WHERE id_menu NOT IN (4,5,6,7,10,12,19,141)";
			$modulosubmenu->fieldTypes("id_menu", "select");
			$modulosubmenu->fieldDataBinding("id_menu", $sql, "id_menu", "nombre_menu", "sql");

			$queryfy->columns = array("nombre_menu", "id_menu");
			$queryfy->where("id_menu", array(4,5,6,7,10,12,19, 141), "NOT IN");
        	$result = $queryfy->select("menu");

			$submenu_asignado_a_menu = array();
			foreach ($result as $row) {
				$submenu_asignado_a_menu[] = array($row['id_menu'], $row['nombre_menu']);
			}

			$modulosubmenu->fieldCssClass("id_menu", array("id_menu"));
			$modulosubmenu->bulkCrudUpdate("id_menu", "select", array("data-cust-attr" =>"some-cust-val"), $submenu_asignado_a_menu);
			$modulosubmenu->bulkCrudUpdate("nombre_submenu", "text",array("data-some-attr" =>"some-dummy-val"));
			$modulosubmenu->bulkCrudUpdate("url_submenu", "text",array("data-some-attr" =>"some-dummy-val"));
			$modulosubmenu->bulkCrudUpdate("area_protegida_submenu", "select", array("data-cust-attr" =>"some-cust-val"),array(
                array(
                    "Si",
                    "Si"
                ),
                array(
                    "No",
                    "No"
                ),
                ));
			$modulosubmenu->setSearchCols(array("nombre_submenu","url_submenu", "icono_submenu", "orden_submenu"));
			$modulosubmenu->crudRemoveCol(array("id_submenu"));
			$modulosubmenu->buttonHide("submitBtnSaveBack");
			$modulosubmenu->fieldTypes("area_protegida_submenu", "select");
			$modulosubmenu->fieldDataBinding("area_protegida_submenu", array("Si" => "Si", "No" => "No"), "", "","array");
			$modulosubmenu->addCallback("format_table_data", "formatTableSubMenu");
			$modulosubmenu->addCallback("before_insert", "insertar_submenu");
			$modulosubmenu->addCallback("after_insert", "despues_insertar_submenu");
			$modulosubmenu->addCallback("before_update", "modificar_submenu");
			$modulosubmenu->addCallback("before_delete", "eliminar_submenu");
			$modulosubmenu->dbOrderBy("orden_submenu asc");
			$render2 = $modulosubmenu->dbTable("submenu")->render();
			$select2submenu = $modulosubmenu->loadPluginJsCode("select2",".id_menu");

			echo $render2;
			echo $select2submenu;
		}
	}

	public function perfil()
	{
		$id = $_SESSION['usuario'][0]["id"];
        $token = $this->token;
		$artify = DB::ArtifyCrud();
		$artify->fieldHideLable("id");
		$artify->fieldCssClass("id", array("d-none"));
		$artify->setSettings("hideAutoIncrement", false);
		$artify->setSettings("required", false);
		$artify->addCallback("before_update", "editar_perfil");
		$artify->fieldGroups("Name",array("nombre","email"));
		$artify->fieldGroups("Name2",array("usuario","password"));
		$artify->fieldGroups("Name3",array("idrol","avatar"));
		$artify->fieldTypes("avatar", "FILE_NEW");
		$artify->fieldTypes("password", "password");
		$artify->fieldRenameLable("nombre", "Nombre Completo");
		$artify->fieldRenameLable("email", "Correo electrónico");
		$artify->fieldRenameLable("password", "Clave de acceso");
		$artify->fieldRenameLable("idrol", "Tipo Usuario");
		$artify->relatedData('idrol','rol','idrol','nombre_rol');
		$artify->formFields(array("id","nombre","email","password","usuario", "idrol", "avatar"));
        $artify->formStaticFields("token_form", "html", "<input type='hidden' name='auth_token' value='" . $token . "' />");
		$artify->fieldDataAttr("password", array("value"=> "", "placeholder" => "*****", "autocomplete" => "new-password"));
		$artify->setPK("id");
		$render = $artify->dbTable("usuario")->render("editform", array("id" => $id));

		View::render(
			"perfil",
			['render' => $render]
		);
	}

	public function dashboard_custom(){
		$artify = DB::ArtifyCrud();
		$artify->addPlugin("select2");
		$artify->formStaticFields("div", "html", "<div class='mostrar_click'></div>");
		$artify->fieldTypes("cantidad_columnas", "select");
		$artify->fieldDataBinding("cantidad_columnas", array(
			"1" => 1,
			"2" => 2,
			"3" => 3,
			"4" => 4,
			"5" => 5,
			"6" => 6
		), "", "","array");
		$artify->fieldNotMandatory("titulo");
		$artify->fieldNotMandatory("icono");
		$artify->fieldNotMandatory("url");
		$artify->setLangData("title_left_join", "Opciones configuración Panel");
		$artify->setLangData("add_row", "Agregar");
		$artify->fieldTypes("icono", "select");
		$artify->fieldCssClass("icono", array("icono"));
		$artify->fieldCssClass("titulo", array("titulo"));
		$artify->fieldCssClass("cantidad_columnas", array("cantidad_columnas"));
		$artify->formFields(array("cantidad_columnas","titulo","icono", "url"));
		$artify->setSettings("template", "dashboard_custom");
		$artify->colRename("id_creador_de_panel", "ID");
		$artify->setSettings("printBtn", false);
		$artify->setSettings("pdfBtn", false);
		$artify->setSettings("csvBtn", false);
		$artify->setSettings("excelBtn", false);
		$artify->setSettings("refresh", false);
		$artify->buttonHide("submitBtnSaveBack");
		$artify->joinTable("custom_panel", "custom_panel.id_creador_de_panel = creador_de_panel.id_creador_de_panel", "LEFT JOIN");
		$render = $artify->dbTable("creador_de_panel")->render();
		$select2 = $artify->loadPluginJsCode("select2",".icono");
		View::render('dashboard_custom', [
			'render' => $render,
			'select2' => $select2
		]);
	}

	public function bot(){
		View::render('bot');
	}

	public function mensajes(){
		$request = new Request();

		if ($request->getMethod() === 'POST') {
			$data = json_decode(file_get_contents("php://input"), true);
			$message = $data["message"];

			$artify = DB::ArtifyCrud();
			$Queryfy = $artify->getQueryfyObj();
			$Queryfy->where("user_message", $message);
			$result = $Queryfy->select("messages");

			$botResponse = "No tengo una respuesta exacta para eso, pero puedo intentar ayudarte.";

			if (!empty($result)) {
				// Obtener la primera respuesta relevante
				$botResponse = $result[0]['bot_response'];
			} else {
				$botResponse = $this->getGeminiResponse($message);
			}

			echo json_encode(["response" => $botResponse]);
		}
	}

	private function getGeminiResponse($message){
		$api_key = "AIzaSyCV3jdhYoIywDeFJmBL-l3EuDnOttX0wLU";
		$data = [
			"contents" => [
				[
					"parts" => [
						["text" => $message]
					]
				]
			]
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

		$response = curl_exec($ch);
		$botResponse = "Lo siento, no puedo responder en este momento.";

		if (curl_errno($ch)) {
			error_log("cURL Error: " . curl_error($ch)); // Guarda el error en logs
		} else {
			$respuesta = json_decode($response, true);
			if (!empty($respuesta['candidates'][0]['content']['parts'][0]['text'])) {
				$botResponse = $respuesta['candidates'][0]['content']['parts'][0]['text'];
			}
		}
		
		curl_close($ch);
		return $botResponse;
	}
}
