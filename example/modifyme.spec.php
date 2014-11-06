<?php
describe('A sandwich', function() {

    $this->sandwich = ['delicious' => true, 'great' => true];

    it('should be delicious', function() {
        assert($this->sandwich['delicious'], "should be delicious");
    });

    it('should be great', function() {
        assert(array_key_exists('great', $this->sandwich), "should be great");
    });

    context('when rad', function() {
        it('should be rad', function() {
            assert(true, "should be rad");
        });
    });

});
