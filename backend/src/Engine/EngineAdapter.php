<?php

declare(strict_types=1);

namespace SoloChess\Engine;

interface EngineAdapter
{
    public function proposeMove(EngineRequest $request): EngineMoveProposal;
}
