<?php
class ExportTable {
		protected $data = array();
		protected $info = array();
		protected $config = array();
		protected $table = '';
		protected $error = '';
		public $l = [];

		
		public function __construct() {
			
			require_once('connect.php');
			require_once 'excel/PHPExcel.php';
			$this->config = @require_once('../config.php');
			$this->l = $this->config['message'];
		}

		public function __destruct() {
			mysql_close();
		}
		
		public function protect($str) {
			$str = get_magic_quotes_gpc()?stripslashes($str):$str;
			$str = mysql_real_escape_string($str);
			return $str;
		}

		public function loadContent($nr, $table = '', $filename = '', $type = '', $columns) {
			if ($nr == 1) {
				
				echo '
				<script>
					$("#selecttable").on("click", function (e) {
						loadContent(2);
					});
				</script>
				<b>'.$this->l['Select table for export'].':</b><br /><br />
				'.$this->getTableSelect().'
				<br /><br />
				<input type="submit" name="selecttable" id="selecttable" value="'.$this->l['next'].'" />';
			} else if ($nr == 2) {
				$columns = $this->getTableColumnsCheckboxes($table);
				if ($columns) {
					echo '
					<script>
						$("input[id*=exportdata]").on("click", function (e) {
							var type = $(e.target).attr("id").split("-")[1];
							if ((type == "xlsx") || (type == "pdf")) {
								$("#type").val(type);
								loadContent(3);
							}
						});
					</script>
					<b>'.$this->l['Choose column'].':</b>
					<div class="checkboxes">'.$columns.'</div>
					<br />
					<input type="hidden" id="type" value="" />
					<input type="button" id="exportdata-xlsx" value="'.$this->l['Export to'].' XLSX" />
					<input type="button" id="exportdata-pdf" value="'.$this->l['Export to'].' PDF" />';
				} else echo $this->getError();
			} else if ($nr == 3) {
				if ($this->prepareData($table, $columns)) {
					if ($path = $this->exportData($filename, $type)) {
						echo $path;
					} else echo $this->getError();
				} else echo $this->getError();
			}
		}
		
		public function getTableSelect() {
			$s = '<select name="table" id="table">';
			$dbQuery = mysql_query('SELECT DATABASE()');
			$dbLine = mysql_fetch_array($dbQuery);
			$db = reset($dbLine);
			if ($db !== null) {
				$tableQuery = mysql_query('SHOW TABLES FROM '.$db);
				$tables = array();
				while ($tableLine = mysql_fetch_array($tableQuery)) {
					$name = $tableLine[0];
					$tables[] = $name;
					if ((isset($this->config['table_alias'])) && (array_key_exists($tableLine[0], $this->config['table_alias']))) {
						$name = $this->config['table_alias'][$tableLine[0]];
					}
					if ((isset($this->config['exclude_table'])) && (isset($this->config['table_list']))) {
						if (in_array($tableLine[0], $this->config['table_list']) == !$this->config['exclude_table']) {
							$s .= '<option value="'.$tableLine[0].'">'.$name.'</option>';
						}
					} else {
						$s .= '<option value="'.$tableLine[0].'">'.$tableLine[0].'</option>';
					}
				}
			} else {
				$this->setError('No database selected');
				return false;
			}
			if ((isset($this->config['query'])) && (is_array($this->config['query']))) {
				foreach ($this->config['query'] as $alias => $query) {
					$matches = array();
					if (preg_match('#^SELECT.+?FROM?\\s*`?(.+?)($|`| |\\()#i', $query, $matches)) {
						$table = $matches[1];
						if (in_array($table, $tables)) {
							$s .= '<option value="query-'.$alias.'">'.$alias.'</option>';
						} else $s .= '<option disabled="disabled" value="query-'.$alias.'">'.$alias.' - '.$this->l['Bad query'].'</option>';
					}
				}
			}
			$s .= '</select>';
			return $s;
		}
		
		public function getTableColumnsCheckboxes($table) {
			$s = '';
			$tableQuery = mysql_query('SHOW COLUMNS FROM '.$this->protect($table));
			if (mysql_error() == '') {
				while ($tableLine = mysql_fetch_array($tableQuery)) {
					$name = $tableLine['Field'];
					if ((isset($this->config['column_alias'])) && (array_key_exists($tableLine['Field'], $this->config['column_alias']))) {
						$name = $this->config['column_alias'][$tableLine['Field']];
					}
					$temp = '<div><input type="checkbox" name="columns[]" class="field-checkbox" value="'.$tableLine['Field'].'" checked="checked">'.$name.'</div>';
					if ((isset($this->config['exclude_column'])) && (isset($this->config['column_list']))) {
						if (in_array($tableLine['Field'], $this->config['column_list']) == !$this->config['exclude_column']) {
							$s .= $temp;
						}
					} else {
						$s .= $temp;
					}
				}
			} else {
				$error = null;
				if (strpos($table, 'query-') !== false) {
					if ((isset($this->config['query'])) && (is_array($this->config['query']))) {
						$temp = explode('query-', $table);
						$alias = $temp[1];
						if ((isset($this->config['query'][$alias])) && (is_string($this->config['query'][$alias]))) {
							$query = $this->protect($this->config['query'][$alias]);
							$q = mysql_query($query);
							if (mysql_error() == '') {
								$line = mysql_fetch_array($q);
								foreach ($line as $key => $value) {
									if (!is_numeric($key)) {
										$name = $key;
										if ((isset($this->config['column_alias'])) && (array_key_exists($key, $this->config['column_alias']))) {
											$name = $this->config['column_alias'][$key];
										}
										$temp = '<div><input type="checkbox" name="columns[]" class="field-checkbox" value="'.$key.'" checked="checked">'.$name.'</div>';
										if ((isset($this->config['exclude_column'])) && (isset($this->config['column_list']))) {
											if (in_array($key, $this->config['column_list']) == !$this->config['exclude_column']) {
												$s .= $temp;
											}
										} else {
											$s .= $temp;
										}
									}
								}
							} else $error = array('message' => $this->l['The query you defined is wrong']);
						} else $error = array('message' => $this->l['Table doesn`t exist']);
					} else $error = array('message' => $this->l['Table doesn`t exist']);
				} else $error = array('message' => $this->l['Table doesn`t exist']);
				if ($error !== null) {
					$this->setError($error['message']);
					return false;
				}
			}
			return $s;
		}
		
		public function prepareData($table, $columns) {
			$s = '';
			foreach ($columns as $column) {
				$s .= $this->protect($column).', ';
			}
			$s = substr($s, 0, -2);
			$query = mysql_query('SELECT '.$s.' FROM '.$this->protect($table));
			if (mysql_error() == '') {
				$tableQuery = mysql_query('SHOW COLUMNS FROM '.$this->protect($table));
				$info = array();
				while ($tableLine = mysql_fetch_array($tableQuery)) {
					if (in_array($tableLine['Field'], $columns)) {
						$info[$tableLine['Field']] = array('type' => $tableLine['Type'], 'width' => 0, 'height' => 0);
					}
				}
				$data = array();
				foreach ($columns as $column) $data[$column] = array();
				while ($line = mysql_fetch_array($query)) {
					foreach ($columns as $column) {
						if (strpos($info[$column]['type'], 'bigint') !== false) {
							if (strlen($line[$column]) > 6) {
								$info[$column]['width'] = 15;
							} else {
								$info[$column]['width'] = 0;
							}
							$info[$column]['height'] = 0;
						} else if (strpos($info[$column]['type'], 'text') !== false) {
							if (strlen($line[$column]) > 100) {
								$info[$column]['width'] = 150;
							} else {
								$info[$column]['width'] = 50;
							}
							$info[$column]['height'] = 0;
						} else if (strpos($info[$column]['type'], 'varchar') !== false) {
							if (strlen($line[$column]) > 50) {
								$info[$column]['width'] = 50;
							} else {
								$info[$column]['width'] = 20;
							}
							$info[$column]['height'] = 0;
						} else {
							$info[$column]['width'] = 0;
							$info[$column]['height'] = 0;
						}
						$data[$column][] = $line[$column];
					}
				}
				$this->setData($data);
				$this->setInfo($info);
				$this->setTable($table);
				return true;
			} else {
				$temp = explode('query-', $table);
				$t = (isset($temp[1]) ? $temp[1] : '');
				if ((isset($this->config['query'][$t])) && (is_string($this->config['query'][$t]))) {
					$matches = array();
					if (preg_match('#^SELECT.+?FROM?\\s*`?(.+?)($|`| |\\()#i', $this->config['query'][$t], $matches)) {
						$tab = $matches[1];
						$dbQuery = mysql_query('SELECT DATABASE()');
						$dbLine = mysql_fetch_array($dbQuery);
						$db = reset($dbLine);
						if ($db !== null) {
							$tableQuery = mysql_query('SHOW TABLES FROM '.$db);
							$tables = array();
							while ($tableLine = mysql_fetch_array($tableQuery)) {
								$tables[] = $tableLine[0];
							}
							if (in_array($tab, $tables)) {
								$tableQuery = mysql_query('SHOW COLUMNS FROM '.$this->protect($tab));
								$info = array();
								while ($tableLine = mysql_fetch_array($tableQuery)) {
									if (in_array($tableLine['Field'], $columns)) {
										$info[$tableLine['Field']] = array('type' => $tableLine['Type'], 'width' => 0, 'height' => 0);
									}
								}
								$data = array();
								foreach ($columns as $column) $data[$column] = array();
								$query = mysql_query($this->config['query'][$t]);
								if (mysql_error() == '') {
									while ($line = mysql_fetch_array($query)) {
										foreach ($columns as $column) {
											if (strpos($info[$column]['type'], 'bigint') !== false) {
												if (strlen($line[$column]) > 6) {
													$info[$column]['width'] = 15;
												} else {
													$info[$column]['width'] = 0;
												}
												$info[$column]['height'] = 0;
											} else if (strpos($info[$column]['type'], 'text') !== false) {
												if (strlen($line[$column]) > 100) {
													$info[$column]['width'] = 150;
												} else {
													$info[$column]['width'] = 50;
												}
												$info[$column]['height'] = 0;
											} else if (strpos($info[$column]['type'], 'varchar') !== false) {
												if (strlen($line[$column]) > 50) {
													$info[$column]['width'] = 50;
												} else {
													$info[$column]['width'] = 20;
												}
												$info[$column]['height'] = 0;
											} else {
												$info[$column]['width'] = 0;
												$info[$column]['height'] = 0;
											}
											$data[$column][] = $line[$column];
										}
									}
									$this->setData($data);
									$this->setInfo($info);
									$this->setTable($t);
									return true;
								} else $this->setError('4');
							} else $this->setError('3');
						} else $this->setError('2');
					} else $this->setError('1');
				}
			}
			$this->setError('Incorrect database query');
			return false;
		}
	
		public function exportData($filename, $type = 'xlsx') {
			if (($type == 'xlsx') || ($type = 'pdf')) {
				if (!is_dir('export')) {
					@mkdir('export', 0777, true);
				}
				if ((isset($this->config['table_alias'])) && (array_key_exists($this->getTable(), $this->config['table_alias']))) {
					$name = $this->config['table_alias'][$this->getTable()];
				} else $name = $this->getTable();
				$path = 'export/'.($filename == '' ? ($name == '' ? uniqid() : $name): $filename);
				if (file_exists($path.'.'.$type)) {
					$path .= '_'.uniqid();
				}
				$path .= '.'.$type;
				$objPHPExcel = new PHPExcel();
				$objPHPExcel->getProperties()->setCreator('User')
											 ->setLastModifiedBy('User')
											 ->setTitle("Database table dump")
											 ->setSubject("Database table dump")
											 ->setDescription("Database table dump generated on ".date('Y-m-d , H:i:s', time()))
											 ->setKeywords("dump")
											 ->setCategory("dumps");
				$objPHPExcel->setActiveSheetIndex(0);
				$objPHPExcel->getActiveSheet()->setTitle(substr($name, 0, 30));
				$column = 0;
				$info = $this->getInfo();
				$objPHPExcel->getActiveSheet()->getStyle('A1:'.chr(65 + count($this->getData()) - 1).'1')->getFont()->setBold(true);
				foreach ($this->getData() as $title => $lines) {
					if ($info[$title]['width'] != 0) $objPHPExcel->getActiveSheet()->getColumnDimensionByColumn($column)->setWidth($info[$title]['width']);
					if ($info[$title]['height'] != 0) $objPHPExcel->getActiveSheet()->getColumnDimensionByRow($row)->setHeight($info[$title]['height']);
					
					if ((isset($this->config['column_alias'])) && (array_key_exists($title, $this->config['column_alias']))) {
						$title = $this->config['column_alias'][$title];
					}
					
					$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column,1, $title);
					$row = 2;
					foreach ($lines as $line) {
						$objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($column,$row, $line);
						$row++;
					}
					$column++;
				}
				$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
				if ($type == 'xlsx') {
					$objWriter->save('../'.$path);
				} else {
					$rendererName = PHPExcel_Settings::PDF_RENDERER_TCPDF;
					$rendererLibrary = 'tcPDF5.9';
					$rendererLibraryPath = dirname(__FILE__).'/tcpdf';
					$objPHPExcel->getActiveSheet()->setShowGridLines(false);
					$objPHPExcel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
					if (count($this->getData()) < 5) {
						$pageSize = PHPExcel_Worksheet_PageSetup::PAPERSIZE_A5;
					} else if (count($this->getData()) < 15) {
						$pageSize = PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4;
					} else if (count($this->getData()) < 25) {
						$pageSize = PHPExcel_Worksheet_PageSetup::PAPERSIZE_A3_EXTRA_PAPER;
					} else if (count($this->getData()) < 30) {
						$pageSize = PHPExcel_Worksheet_PageSetup::PAPERSIZE_A2_PAPER;
					} else {
						$pageSize = PHPExcel_Worksheet_PageSetup::PAPERSIZE_B4;
					}
					$objPHPExcel->getActiveSheet()->getPageSetup()->setPaperSize($pageSize);
					if (!PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath)) {
						die('NOTICE: Please set the $rendererName and $rendererLibraryPath values' . PHP_EOL .'at the top of this script as appropriate for your directory structure');
					}
					$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'PDF');
					$objWriter->setSheetIndex(0);
					$objWriter->save('../'.$path);
				}
				return $path;
			}
			return false;
		}
		
	public function getError() { return $this->error; }
	public function setError($error) { $this->error = $error; return $this; }

	public function getData() { return $this->data; }
	public function setData($data) { $this->data = $data; return $this; }

	public function getTable() { return $this->table; }
	public function setTable($table) { $this->table = $table; return $this; }

	public function getInfo() { return $this->info; }
	public function setInfo($info) { $this->info = $info; return $this; }
	}