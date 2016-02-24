<?php

namespace Laasti\Sessions\Test;

class SessionTest extends \PHPUnit_Framework_TestCase
{
    protected function createSession()
    {
        return new \Laasti\Sessions\Session(new \Laasti\Sessions\Handlers\NullHandler, md5(uniqid('sessions.test', true)));
    }
    
    public function testAddAll()
    {
        $session = $this->createSession();
        
        $session->add(['key1' => 'some test', 'key2' => 'some other test']);
        $this->assertEquals($session->get('key1'), 'some test');
        $this->assertEquals($session->get('key2'), 'some other test');
        $this->assertEquals(array_keys($session->all()), ['key1', 'key2']);
        $this->assertEquals(array_keys($session->all(true)), ['laasti:flashdata.old', 'laasti:flashdata.new', 'key1', 'key2']);
    }
    
    public function testHasGetSetRemove()
    {
        $session = $this->createSession();
        
        $session->set('key1', 'some test');
      //  $this->assertTrue($session->has('key1'));
        $this->assertEquals($session->get('key2', 'default'), 'default');
        //Ensure default's default is null
        $this->assertSame($session->get('key2'), null);
        $this->assertEquals($session->get('key1'), 'some test');
        
        $session->remove('key1');
      //  $this->assertTrue(!$session->has('key1'));
        $this->assertSame($session->get('key1'), null);
    }
    
    public function testClear()
    {
        $session = $this->createSession();
        
        $session->add(['test' => 1]);
        $session->flash('flash', true);
        $session->clear();
        $this->assertSame($session->get('test', 2), 2);
        $this->assertSame($session->all(true)['laasti:flashdata.new']['flash'], true);
        
        $session->add(['test' => 1]);
        $session->flash('flash', true);
        $session->clear(true);
        $this->assertSame($session->get('test', 2), 2);
        $this->assertTrue(!isset($session->all(true)['laasti:flashdata.new']['flash']));
    }
    
    public function testFlashSession()
    {
        $fakeHandler = new \Laasti\Sessions\Handlers\FakeHandler(['laasti:flashdata.new' => ['flashed' => true, 'flashed2' => 123]]);
        $session = new \Laasti\Sessions\Session($fakeHandler, md5(uniqid('sessions.test', true)));
        $this->assertTrue($session->has('flashed'));
        $this->assertTrue($session->get('flashed'));
        $this->assertTrue($session->get('flashed2') === 123);
    }

}
