<?php
describe('A sandwich', function() {

    beforeEach(function() {
        $this->sandwich = ['delicious' => true, 'great' => true];
    });

    it('should be delicious', function() {
        assert($this->sandwich['delicious'], "should be delicious");
    });

    it('should be great', function() {
        assert(array_key_exists('great', $this->sandwich), "should be great");
    });

});
