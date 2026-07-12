<?php

declare(strict_types=1);

namespace SoloChess\Engine;

use RuntimeException;

final class FakeEngineAdapter implements EngineAdapter
{
    /** @var list<EngineMoveProposal> */
    private array $proposals;

    /**
     * @param list<EngineMoveProposal|array{from: string, to: string, promotion?: string}> $proposals
     */
    public function __construct(array $proposals = [])
    {
        $this->proposals = array_map(
            static fn(EngineMoveProposal|array $proposal): EngineMoveProposal => $proposal instanceof EngineMoveProposal
                ? $proposal
                : new EngineMoveProposal($proposal['from'], $proposal['to'], $proposal['promotion'] ?? null),
            $proposals,
        );
    }

    public function proposeMove(EngineRequest $request): EngineMoveProposal
    {
        if ($this->proposals !== []) {
            return array_shift($this->proposals);
        }

        foreach ($request->legalMoves as $from => $destinations) {
            if ($destinations !== []) {
                sort($destinations, SORT_STRING);

                return new EngineMoveProposal($from, $destinations[0]);
            }
        }

        throw new RuntimeException('Fake engine has no legal move to propose.');
    }
}
