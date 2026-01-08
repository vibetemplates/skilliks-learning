/**
 * Voting functionality for projects and features
 * This script handles voting on project list pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle voting
    document.querySelectorAll('.vote-btn').forEach(button => {
        button.addEventListener('click', async function() {
            const type = this.dataset.type;
            const id = this.dataset.id;
            const voteType = this.dataset.vote;
            
            // Check if this button is currently active (user clicking to remove vote)
            const isCurrentlyActive = 
                (voteType === 'up' && this.classList.contains('btn-success')) ||
                (voteType === 'down' && this.classList.contains('btn-danger'));
            
            // Determine action - if clicking active button, unvote; otherwise vote
            const action = isCurrentlyActive ? 'unvote' : 'vote';
            
            try {
                const response = await fetch('/api/vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        id: parseInt(id),
                        vote_type: voteType,
                        action: action
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update vote count display
                    const countElement = document.getElementById(`vote-count-${type}-${id}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    // Update button states
                    const upButton = this.parentElement.querySelector('[data-vote="up"]');
                    const downButton = this.parentElement.querySelector('[data-vote="down"]');
                    
                    // Reset button states
                    upButton.classList.remove('btn-success');
                    upButton.classList.add('btn-outline-success');
                    downButton.classList.remove('btn-danger');
                    downButton.classList.add('btn-outline-danger');
                    
                    // Highlight the active vote
                    if (result.user_vote === 'up') {
                        upButton.classList.remove('btn-outline-success');
                        upButton.classList.add('btn-success');
                    } else if (result.user_vote === 'down') {
                        downButton.classList.remove('btn-outline-danger');
                        downButton.classList.add('btn-danger');
                    }
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                console.error('Voting error:', error);
                alert('Error processing vote. Please try again.');
            }
        });
    });
    
    // Load initial vote states for all voteable items on the page
    async function loadVoteStates() {
        // Get all unique project IDs on the page
        const projectButtons = document.querySelectorAll('.vote-btn[data-type="project"]');
        const projectIds = new Set();
        
        projectButtons.forEach(button => {
            projectIds.add(button.dataset.id);
        });
        
        // Load vote state for each project
        for (const projectId of projectIds) {
            try {
                const response = await fetch(`/api/vote.php?type=project&id=${projectId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Update vote count
                    const countElement = document.getElementById(`vote-count-project-${projectId}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    // Update button states
                    const projectVoteButtons = document.querySelectorAll(`[data-type="project"][data-id="${projectId}"]`);
                    projectVoteButtons.forEach(button => {
                        if (button.dataset.vote === 'up' && result.user_vote === 'up') {
                            button.classList.remove('btn-outline-success');
                            button.classList.add('btn-success');
                        } else if (button.dataset.vote === 'down' && result.user_vote === 'down') {
                            button.classList.remove('btn-outline-danger');
                            button.classList.add('btn-danger');
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading vote state for project:', projectId, error);
            }
        }
        
        // Also load feature vote states if any
        const featureButtons = document.querySelectorAll('.vote-btn[data-type="feature"]');
        const featureIds = new Set();
        
        featureButtons.forEach(button => {
            featureIds.add(button.dataset.id);
        });
        
        for (const featureId of featureIds) {
            try {
                const response = await fetch(`/api/vote.php?type=feature&id=${featureId}`);
                const result = await response.json();
                
                if (result.success) {
                    // Update vote count
                    const countElement = document.getElementById(`vote-count-feature-${featureId}`);
                    if (countElement) {
                        countElement.textContent = result.vote_count;
                    }
                    
                    // Update button states
                    const featureVoteButtons = document.querySelectorAll(`[data-type="feature"][data-id="${featureId}"]`);
                    featureVoteButtons.forEach(button => {
                        if (button.dataset.vote === 'up' && result.user_vote === 'up') {
                            button.classList.remove('btn-outline-success');
                            button.classList.add('btn-success');
                        } else if (button.dataset.vote === 'down' && result.user_vote === 'down') {
                            button.classList.remove('btn-outline-danger');
                            button.classList.add('btn-danger');
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading vote state for feature:', featureId, error);
            }
        }
    }
    
    // Load initial vote states
    loadVoteStates();
});