<?php
class ActionArticle extends Joph_Action {
	public function execute() {
		$str = sprintf("There was an article titled with '%s' and written in %s, %s, a day of %s<br><br>", 
		$this->_schema['title'],
		$this->_schema['year'],
		$this->_schema['Mon'],
		$this->_schema['Day']
		);
		echo $str;
	}
}
