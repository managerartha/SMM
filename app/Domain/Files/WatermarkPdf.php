<?php

namespace App\Domain\Files;

use setasign\Fpdi\Fpdi;

/** FPDI + rotasi teks + transparansi (ExtGState). */
class WatermarkPdf extends Fpdi
{
    protected array $extgstates = [];

    public function rotatedText(float $x, float $y, string $txt, float $angle): void
    {
        $angleRad = deg2rad($angle);
        $c = cos($angleRad);
        $s = sin($angleRad);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        $this->Text($x, $y, $txt);
        $this->_out('Q');
    }

    public function setAlpha(float $alpha): void
    {
        $key = sprintf('%.2F', $alpha);
        if (! isset($this->extgstates[$key])) {
            $this->extgstates[$key] = ['ca' => $alpha, 'CA' => $alpha, 'n' => null];
        }
        $index = array_search($key, array_keys($this->extgstates), true) + 1;
        $this->_out(sprintf('/GS%d gs', $index));
    }

    protected function _putextgstates(): void
    {
        foreach ($this->extgstates as $k => $gs) {
            $this->_newobj();
            $this->extgstates[$k]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $this->_put(sprintf('/ca %.3F', $gs['ca']));
            $this->_put(sprintf('/CA %.3F', $gs['CA']));
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    protected function _putresourcedict(): void
    {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        $i = 1;
        foreach ($this->extgstates as $gs) {
            $this->_put('/GS'.$i++.' '.$gs['n'].' 0 R');
        }
        $this->_put('>>');
    }

    protected function _putresources(): void
    {
        $this->_putextgstates();
        parent::_putresources();
    }
}
