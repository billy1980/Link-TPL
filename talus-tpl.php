<?php
/**
 * Moteur de gestion de TPLs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *      
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *      
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA. 
 *
 * @package Talus' TPL
 * @author Baptiste "Talus" Clavié <clavie.b@gmail.com>
 * @copyright ©Talus, Talus' Works 2006+
 * @link http://www.talus-works.net Talus' Works
 * @license http://www.gnu.org/licenses/lgpl.html LGNU Public License 2+
 * @version $Id$
 */

class Talus_TPL {
  protected
    $_root = './',
    
    $_tpl = '',
    $_last = array(),
    $_included = array(),
    
    $_blocks = array(),
    $_vars = array();
  
  const 
    INCLUDE_TPL = 0,
    REQUIRE_TPL = 1,
    VERSION = '1.7.0';
  
  /**
   * Initialisation. 
   * 
   * @param string $root Le dossier contenant les templates.
   * @param string $cache Le dossier contenant le cache.
   * @return void
   */
  public function __construct($root, $cache){
    // -- Destruction du cache des fichiers de PHP
    clearstatcache();
    
    // -- Init des paramètres
    $this->_last = array();
    $this->_included = array();
    $this->_blocks['.'] = array(array());
    $this->_vars = &$this->_blocks['.'][0];
    
    // -- Mise en place du dossier de templates
    $this->dir($root, $cache);
  }
  
  /**
   * Permet de choisir le dossier contenant les tpls.
   * 
   * @param string $root Le dossier contenant les templates.
   * @param string $cache Le dossier contenant le cache des tpls.
   * @throws Talus_Dir_Exception
   * @return void
   *
   * @since 1.7.0
   */
  public function dir($root = './', $cache = './cache/') {
    // -- On ampute le root du slash final, si il existe.
    $root = rtrim($root, '/');
    
    // -- Le dossier existe-t-il ?
    if (!is_dir($root)) {
      throw new Talus_TPL_Dir_Exception(array('%s n\'est pas un répertoire.', $root), 1);
      return;
    }
    
    if ($root !== $this->_root) {
      $this->_root = $root;
    }
    
    Talus_TPL_Cache::self()->dir($cache);
  }
  
  /**
   * @deprecated
   * @ignore
   */
  public function setDir($root = './', $cache = './cache/'){
    $this->dir($root, $cache);
  }

  /**
   * Définit une ou plusieurs variable.
   * 
   * @param array|string $vars Variable(s) à ajouter
   * @param mixed $value Valeur de la variable si $vars n'est pas un array
   * @return &array
   *
   * @since 1.3.0
   */
  public function &set($vars, $value = null){
    if (is_array($vars)) {
      $this->_vars = array_merge($this->_vars, $vars);
    } elseif ($vars !== null) {
      $this->_vars[$vars] = $value;
    }
    
    return $this->_vars;
  }
  
  /**
   * Définit une variable par référence.
   * 
   * @param mixed $var Nom de la variable à ajouter
   * @param mixed &$value Valeur de la variable à ajouter.
   * @throws Talus_TPL_Var_Exception
   * @return void
   *
   * @since 1.7.0
   */
  public function bind($var, &$value) {
    if (mb_strtolower(gettype($var)) != 'string') {
      throw new Talus_TPL_Var_Exception('Nom de variable référencée invalide.', 3);
      return;
    }
  
    $this->_vars[$var] = &$value;
  }
  
  /**
   * @deprecated
   * @ignore
   */
  public function setRef($var, &$value){
    $this->bind($var, $value);
  }

  /**
   * @deprecated
   * @ignore
   */
  public function setBlock($block, $vars, $value = null){
    $this->block($block, $vars, $value);
  }

  /**
   * Permet d'ajouter une itération d'un bloc et de ses variables
   * Si $vars = null, alors on retourne le bloc
   *
   * @param string $block Nom du bloc à ajouter.
   * @param array|string $vars Variable(s) à assigner à ce bloc
   * @param string $value Valeur de la variable si $vars n'est pas un array
   * @return void
     *
     * @since 1.5.1
   */
  public function &block($block, $vars, $value = null) {
    /*
     * Si le nom du bloc est un bloc racine, et que les vars sont nulles, alors
     * cette méthode joue un rôle de getter, et renvoi le bloc racine en question
     */
    if ($vars === null) {
      if (strpos($block, '.') === false) {
        $return = array();

        if (isset($this->_blocks[$block])) {
          $return = &$this->_blocks[$block];
        }

        return $return;
      }

      throw new Talus_TPL_Block_Exception('Nom de Variable invalide.');
      return null;
    }

    if (!is_array($vars)) {
      $vars = array($vars => $value);
    }
    
    /* 
     * Récupération de tous les blocs, du nombre de blocs, et mise en place d'une
     * référence sur la variable globale des blocs.
     *
     * Le but d'une telle manipulation est de parcourir chaque élément "$current",
     * afin d'accéder au bloc désiré, et permettre ainsi l'initialisation des
     * variables pour la dernière instance du bloc appelé.
     */
    $blocks = explode('.', $block);
    $curBlock = array_pop($blocks); // Bloc à instancier
    $current = &$this->_blocks;
    $cur = array();
    $nbRows = 0;
    
    foreach ($blocks as &$cur) {
      if (!isset($current[$cur])) {
        throw new Talus_TPL_Block_Exception(array('Le bloc %s n\'est pas défini.', $cur), 4);
        return null;
      }
      
      $current = &$current[$cur];
      $current = &$current[count($current) -  1];
    }
    
    if (!isset($current[$curBlock])) {
      $current[$curBlock] = array();
      $nbRows = 0;
    } else {
      $nbRows = count($current[$curBlock]);
    }
    
    /*
     * Variables spécifiques aux blocs (inutilisables autre part) :
     * 
     * FIRST : Est-ce la première itération (true/false) ?
     * LAST : Est-ce la dernière itération (true/false) ?
     * CURRENT : Itération actuelle du bloc.
     * SIZE_OF : Taille totale du bloc (Nombre de répétitions totale)
     *
     * On peut être à la première itération ; mais ce qui est sur, c'est
     * qu'on est forcément à la dernière itération.
     * 
     * Si le nombre d'itération est supérieur à 0, alors ce n'est pas la
     * première itération, et celle d'avant n'était pas la dernière. 
     *
     * Quant au nombre d'itérations (SIZE_OF), il suffit de lier la variable
     * de l'instance actuelle aux autres, et ensuite d'incrémenter cette
     * même variable
     */
    $vars['FIRST'] = true;
    $vars['LAST'] = true;
    $vars['CURRENT'] = $nbRows + 1;
    $vars['SIZE_OF'] = 0;
    
    if ($nbRows > 0) { 
      $vars['FIRST'] = false;
      $current[$curBlock][$nbRows - 1]['LAST'] = false;
      
      $vars['SIZE_OF'] = &$current[$curBlock][0]['SIZE_OF'];
    }
    
    ++$vars['SIZE_OF'];
    $current[$curBlock][] = $vars;

    return $current[$curBlock];
  }

  /** 
   *  Parse un TPL.
   * 
   * @param  mixed $tpl TPL concerné
   * @throws Talus_TPL_Parse_Exception
   * @return bool
   */
  public function parse($tpl){
    // -- Erreur critique si vide
    if (empty($tpl)) {
      throw new Talus_TPL_Parse_Exception('Aucun modèle à parser.', 5);
      return false;
    }
    
    $file = sprintf('%1$s/%2$s', $this->root(), $tpl);
    
    // -- Déclaration du fichier
    if (!isset($this->_last[$file])) {
      if (!is_file($file)) {
        throw new Talus_TPL_Parse_Exception(array('Le modèle %s n\'existe pas.', $tpl), 6);
        return false;
      }

      $this->_last[$file] = filemtime($file);
    }
    
    $cache = Talus_TPL_Cache::self();
    
    $this->_tpl = $tpl;
    $cache->file($this->_tpl, 0);
    
    // -- Si le cache n'existe pas, ou n'est pas valide, on le met à jour.
    if (!$cache->isValid($this->_last[$file])) {
      $cache->put(Talus_TPL_Compiler::self()->compile(file_get_contents($file)));
    }
    
    $cache->exec($this);
    return true;
  }
  
  /**
   * Parse un TPL
   * Implémention de __invoke() pour PHP >= 5.3
   * 
   * @param mixed $tpl TPL concerné
   * @see Talus_TPL::parse()
   * @return void
   */
  public function __invoke($tpl) {
    return $this->parse($tpl);
  }

  /**
   * Parse le TPL, mais renvoi directement le résultat de celui-ci (entièrement
   * parsé, et donc déjà executé par PHP).
   * 
   * @param string $tpl Nom du TPL à parser.
   * @param integer $ttl Temps de vie (en secondes) du cache de niveau 2
   * @return string
   *
   * @todo Cache de niveau 2 ??
   */
  public function pparse($tpl = '', $ttl = 0){
    ob_start();
    $this->parse($tpl);
    return ob_get_clean();
  }

  /**
   * Inclue un TPL : Le parse si nécessaire
   * 
   * @param string $file Fichier à inclure.
   * @param bool $once N'inclure qu'une fois ?
   * @param integer $type Inclusion requise ?
   * @return void
   *
   * @see Talus_TPL_Compiler::compile()
   * @throws Talus_TPL_Runtime_Exception
   * @throws Talus_TPL_Parse_Exception
   */
  public function includeTpl($file, $once = false, $type = self::INCLUDE_TPL){
    // -- Extraction des paramètres
    $qString = '';

    if (strpos($file, '?') !== false) {
      list($file, $qString) = explode('?', $file, 2);
    }

    /*
     * Si un fichier ne doit être présent qu'une seule fois, on regarde si il a
     * déjà été inclus au moins une fois.
     * 
     * Si oui, on ne l'inclue pas ; 
     * Si non, on l'ajoute à la pile des fichiers inclus.
     */
    if ($once){
      $toInclude = sprintf('%1$s/%2$s', $this->root(), $file);
      
      if (in_array($toInclude, $this->_included)) {
        return;
      } else {
        $this->_included[] = $toInclude;
      }
    }

    $data = '';
    $current = array(
      'vars' => $this->_vars,
      'tpl' => $this->_tpl
     );

    try {
      // -- Récupération des paramètres nommés
      $vars = array();
      parse_str($qString, $vars);

      // -- Si MAGIC_QUOTES (grmph), on saute les \ en trop...
      if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
        $vars = array_map('stripslashes', $vars);
      }

      // -- Traitement des paramètres
      $vars = array_change_key_case($vars, CASE_UPPER);
      array_walk($vars, array($this, '_parseParams'));
      $this->set($vars);
      
      $data = $this->pparse($file);
    } catch (Talus_TPL_Parse_Exception $e) {
      $this->_tpl = $current['tpl'];
      $this->_vars = $current['vars'];

      /*
       * Si l'erreur est la n°6 (tpl non existant), et qu'il s'agit d'une balise
       * "require", on renvoit une autre exception (Talus_TPL_Runtime_Exception) ;
       * sinon, on affiche juste le message de l'exception capturée.
       */
      if ($e->getCode() == 6 && $type == self::REQUIRE_TPL) {
        throw new Talus_TPL_Runtime_Exception(array('Ceci était une balise "require" : puisque le template %s n\'existe pas, le script est interrompu.', $file), 7);
        exit;
      } else {
        echo $e->getMessage();
      }
    }

    $this->_tpl = $current['tpl'];
    $this->_vars = $current['vars'];

    echo $data;
  }

  /**
   * Callback pour le array_walk de Talus_TPL::includeTpl()
   * Vérifie si une variable ne s'est pas glissé dans le tas, et l'interprete
   * le cas échéant.
   *
   * @param mixed &$input Valeur pour la clé $key
   * @param mixed $key Valeur de la clé courante
   * @return void
   */
  protected function _parseParams(&$input, $key) {
    static $prefLen = 0;

    if ($prefLen == 0) {
      $prefLen = mb_strlen('$__tpl_vars__');
    }

    if (mb_substr($input, 0, $prefLen) == '$__tpl_vars__') {
      $input = $this->_vars[mb_substr($input, $prefLen)];
    }
  }
  
  /**
   * Getter pour $this->_root
   *
   * @return string
   */
  public function root() {
    return $this->_root;
  }
  
  /**
   * @deprecated
   * @ignore
   */
  public function getRootDir(){
    return $this->root();
  }
  
  /**
   * @deprecated
   * @ognore
   */
  public function getCacheDir() {
    return Talus_TPL_Cache::self()->dir();
  }
  
  /**
   * @deprecated
   * @ignore
   */
  public function &getBlock($block){
    return $this->block($block, null);
  }
  
  /**
   * @deprecated
   * @ignore
   */
  public function setNamespace($namespace = null) {
    Talus_TPL_Compiler::self()->parameter('namespace', $namespace);
  }
}

/*
 * EOF
 */
