<?php
class ActionFooter extends Joph_Action {
	public function execute() {
		echo "powered by Joph " . Joph_Framework::VERSION . "<br><br>";
	}
}
