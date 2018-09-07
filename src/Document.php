<?php

namespace OpenFoodFacts;

class Document{

  private $data;

  public function __construct(array $data){
    $this->data = $data;
  }
  public function __get ( string $name ){
      return $this->data[$name];
  }
  public function __isset ( string $name ):bool{
      return isset($this->data[$name]);
  }

}
