<?php
defined('ROOT_DIR') || exit;

class Tag
{
	const BEFORE_HEADER_CSS = 1;
	const AFTER_HEADER_CSS = 2;
	const BEFORE_HEADER_JS = 4;
	const AFTER_HEADER_JS = 8;
	const BEFORE_FOOTER_JS = 16;
	const AFTER_FOOTER_JS = 32;

	private static $html_title = '';
	private static $type = array();
	private static $html_meta = array();
	private static $html_css = array();
	private static $html_js = array();
	private static $html_footer_js = array();
	private static $html_dynamic_assets = array(1 => '', 2 => '', 4 => '', 8 => '', 16 => '', 32 => '');

	public static function setHtmlTitle($title)
	{
		self::$html_title = $title;
	}

	public static function getHtmlTitle()
	{
		return self::$html_title;
	}

	public static function addAsset($asset, $type, $key = false, $overwrite = false)
	{
		$type = 'html_' . $type;
		if ($key) {
			if (isset(self::${$type}[$key]) && !$overwrite) return;
			self::${$type}[$key] = $asset;
		} elseif (!in_array($asset, self::$type)) {
			self::${$type}[] = $asset;
		}
	}

	public static function addMetaTag($tag = '', $key = false, $overwrite = false)
	{
		self::addAsset($tag, 'meta', $key, $overwrite);
	}

	public static function setMetaKeywords($keywords = '')
	{
		self::addMetaTag("<meta name=\"keywords\" content=\"$keywords\">", 'MetaKeywords', true);
	}

	public static function setMetaDescription($description = '')
	{
		self::addMetaTag("<meta name=\"description\" content=\"$description\">", 'MetaDescription', true);
	}

	public static function addCSS($css, $key = false, $overwrite = false)
	{
		self::addAsset($css, 'css', $key, $overwrite);
	}

	public static function addJS($js, $key = false, $overwrite = false)
	{
		self::addAsset($js, 'js', $key, $overwrite);
	}

	public static function addFooterJS($js, $key = false, $overwrite = false)
	{
		self::addAsset($js, 'footer_js', $key, $overwrite);
	}

	public static function addDynamicAsset($asset, $pos)
	{
		if (isset(self::$html_dynamic_assets[$pos]))
			self::$html_dynamic_assets[$pos] .= $asset;
	}

	public static function addDynamicCSS($css, $pos)
	{
		self::addDynamicAsset($css, $pos);
	}

	public static function addDynamicJS($js, $pos)
	{
		self::addDynamicAsset($js, $pos);
	}

	public static function unShiftCSS($css, $key = false, $overwrite = false)
	{
		if ($key) {
			if (isset(self::$html_css[$key])) {
				if ($overwrite)
					unset(self::$html_css[$key]);
				else return;
			}
			$css = array($key => $css);
		} else $css = array($css);
		self::$html_css = array_merge($css, self::$html_css);
	}

	public static function unShiftJS($js, $key = false, $overwrite = false)
	{
		if ($key) {
			if (isset(self::$html_js[$key])) {
				if ($overwrite)
					unset(self::$html_js[$key]);
				else return;
			}
			$js = array($key => $js);
		} else $js = array($js);
		self::$html_js = array_merge($js, self::$html_js);
	}

	public static function unShiftFooterJS($js, $key = false, $overwrite = false)
	{
		if ($key) {
			if (isset(self::$html_footer_js[$key])) {
				if ($overwrite)
					unset(self::$html_footer_js[$key]);
				else return;
			}
			$js = array($key => $js);
		} else $js = array($js);
		self::$html_footer_js = array_merge($js, self::$html_footer_js);
	}

	public static function getHtmlHeader()
	{
		$html = '<base href="' . BASE_URL . "\">\n";
		if (sizeof(self::$html_meta)) {
			foreach (self::$html_meta as $metaTag)
				$html .= $metaTag . "\n";
			unset($metaTag);
		}

		if (self::$html_dynamic_assets[self::BEFORE_HEADER_CSS]) {
			$html .= "<style type=\"text/css\">\n" .
				self::$html_dynamic_assets[self::BEFORE_HEADER_CSS] . "\n</style>\n";
		}

		if (sizeof(self::$html_css)) {
			if (ASSETS_OPTIMIZATION & 1) {
				$maxTime = 0;
				$nameMd5 = '';
				foreach (self::$html_css as $css) {
					$nameMd5 .= $css;
					if (strrpos($css, '{') === false) {
						if (strrpos($css, '/') === false) {
							$css = DEFAULT_CSS_DIR . $css;
							if (file_exists($css)) {
								$mTime = filemtime($css);
								if ($maxTime < $mTime) $maxTime = $mTime;
							}
						} elseif (preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $css)) {
							$file = CSS_CACHE_DIR . preg_replace('/[^a-z0-9\.]+/i', '-', $css);
							if (file_exists($file)) {
								$tmp = file_get_contents($file);
							} else {
								if (!preg_match('/^http/i', $css)) $css = SCHEME . ':' . $css;
								$tmp = @file_get_contents($css);
								$tmp = CssMin::minify($tmp);
								//if (!is_dir(CSS_CACHE_DIR)) mkdir(CSS_CACHE_DIR, DIR_WRITE_MODE, true);
								File::mkDir(CSS_CACHE_DIR);
								file_put_contents($file, $tmp);
							}
							if (preg_match('/:\s*url\s*\(/i', $tmp)) {
								$html .= "<link href=\"$css?__av=" . ASSETS_VERSION . "\" rel=\"stylesheet\" type=\"text/css\" />\n";
							}
						} else {
							$css = PUBLIC_DIR . $css;
							if (file_exists($css)) {
								$mTime = filemtime($css);
								if ($maxTime < $mTime) $maxTime = $mTime;
							}
						}
					}
				}

				$nameMd5 = md5($nameMd5);
				$file = CSS_CACHE_DIR . $nameMd5 . '.css';
				if (!file_exists($file) || (ENVIRONMENT != 'Production' && $maxTime > filemtime($file))) {
					$cache = '';
					foreach (self::$html_css as $css) {
						if (strrpos($css, '{') !== false) {
							$cache .= $css;
						} elseif (strrpos($css, '/') === false) {
							$css = DEFAULT_CSS_DIR . $css;
							if (file_exists($css)) {
								if (ASSETS_OPTIMIZATION & 2) $cache .= self::minAsset($css, true) . "\n";
								else $cache .= file_get_contents($css) . "\n";
							}
						} elseif (preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $css)) {
							$file = CSS_CACHE_DIR . preg_replace('/[^a-z0-9\.]+/i', '-', $css);
							if (file_exists($file)) {
								$css = file_get_contents($file);
							} else {
								if (!preg_match('/^http/i', $css)) $css = SCHEME . ':' . $css;
								$css = @file_get_contents($css);
								if (ASSETS_OPTIMIZATION & 2) $css = CssMin::minify($css);
								//if (!is_dir(CSS_CACHE_DIR)) mkdir(CSS_CACHE_DIR, DIR_WRITE_MODE, true);
								File::mkDir(CSS_CACHE_DIR);
								file_put_contents($file, $css);
							}
							if (!preg_match('/:\s*url\s*\(/i', $css)) {
								$cache .= $css;
							}
						} elseif (file_exists($css)) {
							if (ASSETS_OPTIMIZATION & 2) $tmp = self::minAsset($css, true);
							else $tmp = file_get_contents($css);
							$cache .= preg_replace('/url\s*\(\s*([\'"])/i', 'url($1../' . dirname($css) . '/', $tmp);
						}
					}
					$cache = str_replace(array('"../', '\'../'), array('"../../', '\'../../'), $cache);
					//if (!is_dir(CSS_CACHE_DIR)) mkdir(CSS_CACHE_DIR, DIR_WRITE_MODE, true);
					File::mkDir(CSS_CACHE_DIR);
					$file = CSS_CACHE_DIR . $nameMd5 . '.css';
					file_put_contents($file, $cache);
				}

				$file = "css/cache/$nameMd5.css?__av=" . ASSETS_VERSION;
				$html .= "<link href=\"$file\" rel=\"stylesheet\" type=\"text/css\" />\n";
			} else {
				foreach (self::$html_css as $css) {
					if (strrpos($css, '{') === false) {
						if (strrpos($css, '/') === false) $css = "css/$css";
						if (ASSETS_OPTIMIZATION & 2 && !preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $css)) $css = self::minAsset($css);
						$css .= '?__av=' . ASSETS_VERSION;
						$html .= "<link href=\"$css\" rel=\"stylesheet\" type=\"text/css\" />\n";
					} else {
						$html .= "<style type=\"text/css\">\n{$css}\n</style>\n";
					}
				}
			}
		}

		if (self::$html_dynamic_assets[self::AFTER_HEADER_CSS]) {
			$html .= "<style type=\"text/css\">\n" .
				self::$html_dynamic_assets[self::AFTER_HEADER_CSS] . "\n</style>\n";
		}

		if (self::$html_dynamic_assets[self::BEFORE_HEADER_JS]) {
			$html .= "<script type=\"text/javascript\" language=\"javascript\">\n"
				. self::$html_dynamic_assets[self::BEFORE_HEADER_JS] . "\n</script>\n";
		}

		if (sizeof(self::$html_js)) {
			$html .= self::getJSHtml(self::$html_js);
		}

		if (self::$html_dynamic_assets[self::AFTER_HEADER_JS]) {
			$html .= "<script type=\"text/javascript\" language=\"javascript\">\n"
				. self::$html_dynamic_assets[self::AFTER_HEADER_JS] . "\n</script>\n";
		}

		return $html;
	}

	public static function getHtmlFooter()
	{
		if (self::$html_dynamic_assets[self::BEFORE_FOOTER_JS]) {
			$html = "<script type=\"text/javascript\" language=\"javascript\">\n"
				. self::$html_dynamic_assets[self::BEFORE_FOOTER_JS] . "\n</script>\n";
		} else $html = '';

		$html .= self::getJSHtml(self::$html_footer_js);

		if (self::$html_dynamic_assets[self::AFTER_FOOTER_JS]) {
			$html .= "<script type=\"text/javascript\" language=\"javascript\">\n"
				. self::$html_dynamic_assets[self::AFTER_FOOTER_JS] . "\n</script>\n";
		}

		return $html;
	}

	private static function getJSHtml(&$jss)
	{
		$html = '';
		if (sizeof($jss)) {
			if (ASSETS_OPTIMIZATION & 4) {
				$maxTime = 0;
				$nameMd5 = '';
				foreach ($jss as $js) {
					$nameMd5 .= $js;
					if (!preg_match('/[;\(]/', $js)) {
						if (strrpos($js, '/') === false) {
							$js = DEFAULT_JS_DIR . $js;
							if (file_exists($js)) {
								$mTime = filemtime($js);
								if ($maxTime < $mTime) $maxTime = $mTime;
							}
						} elseif (preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $js)) {
							$file = JS_CACHE_DIR . preg_replace('/[^a-z0-9\.]+/i', '-', $js);
							if (!file_exists($file)) {
								if (!preg_match('/^http/i', $js)) $js = SCHEME . ':' . $js;
								$js = @file_get_contents($js);
								$js = JSMin::minify($js);
								//if (!is_dir(JS_CACHE_DIR)) mkdir(JS_CACHE_DIR, DIR_WRITE_MODE, true);
								File::mkDir(JS_CACHE_DIR);
								file_put_contents($file, $js);
							}
						} else {
							$js = PUBLIC_DIR . $js;
							if (file_exists($js)) {
								$mTime = filemtime($js);
								if ($maxTime < $mTime) $maxTime = $mTime;
							}
						}
					}
				}

				$nameMd5 = md5($nameMd5);
				$file = JS_CACHE_DIR . $nameMd5 . '.js';
				if (!file_exists($file) || (ENVIRONMENT != 'Production' && $maxTime > filemtime($file))) {
					$cache = '';
					foreach ($jss as $js) {
						if (preg_match('/[;\(]/', $js)) {
							$cache .= $js . "\n";
						} elseif (strrpos($js, '/') === false) {
							$js = DEFAULT_JS_DIR . $js;
							if (file_exists($js)) {
								if (ASSETS_OPTIMIZATION & 8) $cache .= self::minAsset($js, true) . "\n";
								else $cache .= file_get_contents($js) . "\n";
							}
						} elseif (preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $js)) {
							$file = JS_CACHE_DIR . preg_replace('/[^a-z0-9\.]+/i', '-', $js);
							if (file_exists($file)) {
								$js = file_get_contents($file);
							} else {
								if (!preg_match('/^http/i', $js)) $js = SCHEME . ':' . $js;
								$js = @file_get_contents($js);
								$js = JSMin::minify($js);
								//if (!is_dir(JS_CACHE_DIR)) mkdir(JS_CACHE_DIR, DIR_WRITE_MODE, true);
								File::mkDir(JS_CACHE_DIR);
								file_put_contents($file, $js);
							}
							$cache .= $js . "\n";
						} elseif (file_exists($js)) {
							if (ASSETS_OPTIMIZATION & 8) $cache .= self::minAsset($js, true) . "\n";
							else $cache .= file_get_contents($js) . "\n";
						}
					}

					//if (!is_dir(JS_CACHE_DIR)) mkdir(JS_CACHE_DIR, DIR_WRITE_MODE, true);
					File::mkDir(JS_CACHE_DIR);
					$file = JS_CACHE_DIR . $nameMd5 . '.js';
					file_put_contents($file, $cache);
				}

				$file = "js/cache/$nameMd5.js?__av=" . ASSETS_VERSION;
				$html .= "<script src=\"$file\" type=\"text/javascript\" language=\"javascript\"></script>\n";
			} else {
				foreach ($jss as $js) {
					if (preg_match('/[;\(]/', $js)) {
						$html .= "<script type=\"text/javascript\" language=\"javascript\">\n{$js}\n</script>\n";
					} else {
						if (strrpos($js, '/') === false) $js = "js/$js";
						if (ASSETS_OPTIMIZATION & 2 && !preg_match('/^https?:\/\/|\/\/([\da-z\.-]+)\.([a-z\.]{2,6})/i', $js)) $js = self::minAsset($js);
						$js .= '?v=' . ASSETS_VERSION;
						$html .= "<script src=\"$js\" type=\"text/javascript\" language=\"javascript\"></script>\n";
					}
				}
			}
		}
		return $html;
	}

	private static function minAsset($file, $returnContent = false)
	{
		$pathInfo = pathinfo($file);
		if (substr($pathInfo['filename'], -4) == '.min')
			return ($returnContent ? file_get_contents($file) : $file);
		$minFile = "$pathInfo[dirname]/$pathInfo[filename].min.$pathInfo[extension]";
		if (file_exists($minFile) && (ENVIRONMENT == 'Production' || filemtime($minFile) > filemtime($file)))
			return ($returnContent ? file_get_contents($minFile) : $minFile);
		switch (strtolower($pathInfo['extension'])) {
			case 'css':
				$minContent = CssMin::minify(file_get_contents($file));
				break;
			case 'js':
				$minContent = JSMin::minify(file_get_contents($file));
				break;
			default:
				return ($returnContent ? file_get_contents($file) : $file);
		}
		file_put_contents($minFile, $minContent);
		return ($returnContent ? $minContent : $minFile);
	}
}